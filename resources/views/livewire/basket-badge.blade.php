<a href="{{ route('basket.show') }}" class="relative hover:underline">
    Basket
    @if ($count > 0)
        <span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-white">{{ $count }}</span>
    @endif
</a>
