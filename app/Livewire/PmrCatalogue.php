<?php

namespace App\Livewire;

use App\Enums\ReportType;
use App\Models\Report;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.public')]
class PmrCatalogue extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updating($name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $reports = Report::query()
            ->where('type', ReportType::PMR)
            ->with('market')
            ->when($this->search !== '', fn ($q) => $q->whereHas('market', fn ($m) => $m->where('name', 'like', '%'.$this->search.'%')))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.pmr-catalogue', ['reports' => $reports]);
    }
}
