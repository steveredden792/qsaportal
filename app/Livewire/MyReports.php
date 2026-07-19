<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.public')]
class MyReports extends Component
{
    public function render(): View
    {
        return view('livewire.my-reports', [
            'entitlements' => auth()->user()->entitlements()
                ->with('issue.report', 'issue.assets')
                ->latest()
                ->get(),
        ]);
    }
}
