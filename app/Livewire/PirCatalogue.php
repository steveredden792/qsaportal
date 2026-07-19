<?php

namespace App\Livewire;

use App\Enums\ReportType;
use App\Models\Charity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.public')]
class PirCatalogue extends Component
{
    use WithPagination;

    private const SORTABLE = ['name', 'latest_q_score', 'latest_stability'];

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $qMin = null;

    #[Url]
    public ?int $qMax = null;

    #[Url]
    public ?int $stabilityMin = null;

    #[Url]
    public ?int $stabilityMax = null;

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDir = 'asc';

    public function updating($name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
    }

    public function render(): View
    {
        $sortField = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'name';

        $charities = Charity::query()
            ->whereHas('report', fn ($q) => $q->where('type', ReportType::PIR))
            ->with('report:id,charity_id,type,slug,name')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('cc_ref', 'like', $term));
            })
            ->when($this->qMin !== null, fn ($q) => $q->where('latest_q_score', '>=', $this->qMin))
            ->when($this->qMax !== null, fn ($q) => $q->where('latest_q_score', '<=', $this->qMax))
            ->when($this->stabilityMin !== null, fn ($q) => $q->where('latest_stability', '>=', $this->stabilityMin))
            ->when($this->stabilityMax !== null, fn ($q) => $q->where('latest_stability', '<=', $this->stabilityMax))
            ->orderBy($sortField, $this->sortDir === 'desc' ? 'desc' : 'asc')
            ->paginate(20);

        return view('livewire.pir-catalogue', ['charities' => $charities]);
    }
}
