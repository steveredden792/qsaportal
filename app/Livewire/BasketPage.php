<?php

namespace App\Livewire;

use App\Support\Basket;
use App\Support\Pricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.public', ['title' => 'Your Cart'])]
class BasketPage extends Component
{
    public function remove(int $reportId): void
    {
        Basket::removeReport($reportId);

        $this->dispatch('basket-updated');
    }

    public function render(): View
    {
        $reports = Basket::reports();
        $price = Pricing::for('pir', 'single');

        return view('livewire.basket-page', [
            'reports' => $reports,
            'price' => $price,
            'total' => $price * $reports->count(),
        ]);
    }
}
