<?php

use App\Livewire\AddToBasket;
use App\Livewire\BasketPage;
use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function pirWithIssue(): Report
{
    $report = Report::factory()->pir()->create();
    Issue::factory()->for($report)->create(['is_current' => true]);

    return $report->fresh();
}

it('adds a report to the basket idempotently', function () {
    $report = pirWithIssue();

    Livewire::test(AddToBasket::class, ['report' => $report])->call('add');
    Livewire::test(AddToBasket::class, ['report' => $report])->call('add');

    expect(BasketItem::where('user_id', $this->user->id)->count())->toBe(1);
});

it('shows In basket after adding and hides the button when the current issue is owned', function () {
    $report = pirWithIssue();

    Livewire::test(AddToBasket::class, ['report' => $report])
        ->assertSee('Add to basket')
        ->call('add')
        ->assertSee('In basket');

    Entitlement::factory()->create([
        'user_id' => $this->user->id,
        'issue_id' => $report->currentIssue->id,
    ]);

    Livewire::test(AddToBasket::class, ['report' => $report])
        ->assertDontSee('Add to basket')
        ->assertDontSee('In basket');
});

it('lists basket lines with total and removes lines', function () {
    $reports = collect([pirWithIssue(), pirWithIssue()]);
    $reports->each(fn (Report $r) => BasketItem::create([
        'user_id' => $this->user->id,
        'report_id' => $r->id,
    ]));

    $line = BasketItem::where('report_id', $reports[0]->id)->first();

    Livewire::test(BasketPage::class)
        ->assertSee($reports[0]->name)
        ->assertSee($reports[1]->name)
        ->assertSee('£50.00')
        ->call('remove', $line->id)
        ->assertDontSee($reports[0]->name)
        ->assertSee('£25.00');
});

it('cannot remove another users basket line', function () {
    $other = BasketItem::factory()->create();

    Livewire::test(BasketPage::class)->call('remove', $other->id);

    expect(BasketItem::whereKey($other->id)->exists())->toBeTrue();
});

it('redirects guests away from the basket page', function () {
    auth()->logout();

    $this->get(route('basket.show'))->assertRedirect(route('login'));
});
