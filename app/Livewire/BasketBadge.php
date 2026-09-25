<?php

namespace App\Livewire;

use App\Support\Basket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class BasketBadge extends Component
{
    #[On('basket-updated')]
    public function refresh(): void
    {
        // Re-render with a fresh count.
    }

    public function render(): View
    {
        return view('livewire.basket-badge', [
            'count' => Basket::count(),
        ]);
    }
}
