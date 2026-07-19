<x-public title="Checkout">
    <h1 class="text-2xl font-bold text-brand">Thank you</h1>
    @if ($order->status === 'fulfilled')
        <p class="mt-4">Payment confirmed — your reports are in
            <a href="{{ route('my-reports') }}" class="text-brand hover:underline">My reports</a>.</p>
    @else
        <p class="mt-4">Payment processing — check back shortly. Your reports will appear in My reports once payment is confirmed.</p>
    @endif
</x-public>
