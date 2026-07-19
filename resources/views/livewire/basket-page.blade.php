<div>
    <h1 class="mb-6 text-2xl font-bold text-brand">Your basket</h1>

    @if (session('error'))
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($items->isEmpty())
        <p class="text-gray-600">Your basket is empty.
            <a href="{{ route('catalogue.pir') }}" class="text-brand hover:underline">Browse the PIR database</a>.</p>
    @else
        <div class="overflow-x-auto rounded border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <tbody class="divide-y divide-gray-100">
                    @foreach ($items as $item)
                        <tr wire:key="basket-{{ $item->id }}">
                            <td class="px-4 py-3 font-medium">{{ $item->report->name }}</td>
                            <td class="px-4 py-3 text-right">{{ \App\Support\Money::format($price) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="remove({{ $item->id }})" class="text-sm text-red-600 hover:underline">Remove</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right">{{ \App\Support\Money::format($total) }}</td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-6">
            {{-- Replaced with the real checkout form in the checkout task --}}
            <button class="rounded bg-brand px-5 py-2 font-medium text-white" disabled title="Checkout arrives next">Checkout</button>
        </div>
    @endif
</div>
