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
class PprCatalogue extends Component
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
            ->where('type', ReportType::PPR)
            ->with('provider')
            ->when($this->search !== '', fn ($q) => $q->whereHas('provider', fn ($p) => $p->where('name', 'like', '%'.$this->search.'%')))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.ppr-catalogue', ['reports' => $reports]);
    }
}
