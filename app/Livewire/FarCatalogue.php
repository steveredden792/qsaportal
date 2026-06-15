<?php

namespace App\Livewire;

use App\Models\Charity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.public')]
class FarCatalogue extends Component
{
    use WithPagination;

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
        if (! in_array($field, ['name', 'latest_q_score', 'latest_stability'], true)) {
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
        $charities = Charity::query()
            ->whereHas('report', fn ($q) => $q->where('type', 'far'))
            ->with('report:id,charity_id,slug')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('cc_ref', 'like', $term));
            })
            ->when($this->qMin !== null, fn ($q) => $q->where('latest_q_score', '>=', $this->qMin))
            ->when($this->qMax !== null, fn ($q) => $q->where('latest_q_score', '<=', $this->qMax))
            ->when($this->stabilityMin !== null, fn ($q) => $q->where('latest_stability', '>=', $this->stabilityMin))
            ->when($this->stabilityMax !== null, fn ($q) => $q->where('latest_stability', '<=', $this->stabilityMax))
            ->orderBy($this->sortField, $this->sortDir === 'desc' ? 'desc' : 'asc')
            ->paginate(20);

        return view('livewire.far-catalogue', ['charities' => $charities]);
    }
}
