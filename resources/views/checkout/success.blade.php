<x-public title="Checkout" subtitle="Order complete">
    <div class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
        <h2 class="font-heading text-2xl font-semibold text-slate-900">Thank you for your order</h2>

        @if ($order->status === 'fulfilled')
            <p class="mt-4 text-slate-600">
                Payment confirmed. Your reports are now available in your account and ready to download.
            </p>
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <p class="font-semibold text-slate-900">What happens next</p>
                <p class="mt-2">Open My reports to access your purchased documents, review the report status and download the files whenever you need them.</p>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('my-reports') }}" class="rounded-full bg-brand px-5 py-2.5 font-semibold text-white transition hover:bg-brand-light">Open My reports</a>
                <a href="{{ route('catalogue.pir') }}" class="rounded-full border border-slate-300 px-5 py-2.5 font-semibold text-slate-700 transition hover:border-brand hover:text-brand">Browse more reports</a>
            </div>
        @else
            <p class="mt-4 text-slate-600">
                Payment is still being confirmed. Your reports will appear in My reports as soon as the payment is processed.
            </p>
            <div class="mt-6">
                <a href="{{ route('my-reports') }}" class="rounded-full bg-brand px-5 py-2.5 font-semibold text-white transition hover:bg-brand-light">View My reports</a>
            </div>
        @endif
    </div>
</x-public>
