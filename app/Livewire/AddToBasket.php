<?php

namespace App\Livewire;

use App\Enums\AssetType;
use App\Models\BasketItem;
use App\Models\Report;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AddToBasket extends Component
{
    public Report $report;

    public function add(): void
    {
        BasketItem::firstOrCreate([
            'user_id' => auth()->id(),
            'report_id' => $this->report->id,
        ]);

        $this->dispatch('basket-updated');
    }

    public function render(): View
    {
        $issue = $this->report->currentIssue()->with('assets')->first();

        $hasEntitlement = $issue !== null
            && auth()->user()->entitlements()->active()->where('issue_id', $issue->id)->exists();

        $ownedPdf = $hasEntitlement ? $issue->assets->firstWhere('type', AssetType::ReportPdf) : null;

        return view('livewire.add-to-basket', [
            'owned' => $ownedPdf !== null,
            'ownedPdf' => $ownedPdf,
            'inBasket' => ! $hasEntitlement && BasketItem::where('user_id', auth()->id())
                ->where('report_id', $this->report->id)
                ->exists(),
            'purchasable' => $issue !== null && ! $hasEntitlement,
        ]);
    }
}
