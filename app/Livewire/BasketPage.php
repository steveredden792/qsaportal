<?php

namespace App\Livewire;

use App\Models\BasketItem;
use App\Support\Pricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.public')]
class BasketPage extends Component
{
    public function remove(int $basketItemId): void
    {
        BasketItem::where('user_id', auth()->id())->whereKey($basketItemId)->delete();

        $this->dispatch('basket-updated');
    }

    public function render(): View
    {
        $items = BasketItem::with('report')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        $price = Pricing::for('pir', 'single');

        return view('livewire.basket-page', [
            'items' => $items,
            'price' => $price,
            'total' => $price * $items->count(),
        ]);
    }
}
