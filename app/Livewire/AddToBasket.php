<?php

namespace App\Livewire;

use App\Enums\AssetType;
use App\Models\Report;
use App\Support\Basket;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AddToBasket extends Component
{
    public Report $report;

    public function add(): void
    {
        Basket::add($this->report);

        $this->dispatch('basket-updated');
    }

    public function render(): View
    {
        $user = auth()->user();
        $issue = $this->report->currentIssue()->with('assets')->first();

        $hasEntitlement = $user !== null
            && $issue !== null
            && $user->entitlements()->active()->where('issue_id', $issue->id)->exists();

        $ownedPdf = $hasEntitlement ? $issue->assets->firstWhere('type', AssetType::ReportPdf) : null;

        return view('livewire.add-to-basket', [
            'owned' => $ownedPdf !== null,
            'ownedPdf' => $ownedPdf,
            'inBasket' => ! $hasEntitlement && Basket::contains($this->report->id),
            'purchasable' => $issue !== null && ! $hasEntitlement,
        ]);
    }
}
