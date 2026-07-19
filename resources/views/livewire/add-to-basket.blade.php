<span>
    @if ($owned)
        <a href="{{ route('assets.download', $ownedPdf) }}" class="rounded bg-brand px-4 py-2 text-sm font-medium text-white">Download</a>
    @elseif ($inBasket)
        <a href="{{ route('basket.show') }}" class="rounded border border-brand px-4 py-2 text-sm font-medium text-brand">In basket</a>
    @elseif ($purchasable)
        <button wire:click="add" class="rounded bg-brand px-4 py-2 text-sm font-medium text-white">Add to basket</button>
    @endif
</span>
