<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand">Purchase</p>
                <h1 class="mt-2 text-3xl font-semibold text-slate-900">Your basket</h1>
                <p class="mt-2 text-sm text-slate-600">Review your selected reports before continuing to checkout.</p>
            </div>
            <div class="rounded-full border border-brand/20 bg-brand/5 px-4 py-2 text-sm font-medium text-brand">
                Review your selected reports
            </div>
        </div>
    </div>

    @if (session('error'))
        <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($items->isEmpty())
        <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-slate-600">Your basket is empty.</p>
            <a href="{{ route('catalogue.pir') }}" class="mt-4 inline-flex text-brand transition hover:text-brand-light">Browse the PIR database</a>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($items as $item)
                        <tr wire:key="basket-{{ $item->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $item->report->name }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ \App\Support\Money::format($price) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="remove({{ $item->id }})" class="text-sm font-medium text-red-600 transition hover:text-red-700">Remove</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-semibold text-slate-900">
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right">{{ \App\Support\Money::format($total) }}</td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">Checkout</p>
                <p class="mt-1 text-sm text-slate-600">Securely continue to the payment step for your selected reports.</p>
            </div>
            <form method="POST" action="{{ route('checkout.store') }}">
                @csrf
                <button type="submit" class="rounded-full bg-brand px-5 py-2.5 font-semibold text-white transition hover:bg-brand-light">Continue to checkout</button>
            </form>
        </div>
    @endif
</div>
