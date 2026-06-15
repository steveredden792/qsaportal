<div>
    <h1 class="mb-6 text-2xl font-bold text-brand">Provider Portfolio Reports</h1>

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search provider name"
               class="w-full max-w-sm rounded border-gray-300">
    </div>

    <div class="divide-y divide-gray-100 rounded border border-gray-200 bg-white">
        @forelse ($reports as $report)
            <div class="flex items-center justify-between px-4 py-3" wire:key="ppr-{{ $report->id }}">
                <a href="{{ route('reports.show', $report->slug) }}" class="font-medium text-brand hover:underline">
                    {{ $report->provider->name }}
                </a>
                <span class="text-sm text-gray-600">from {{ \App\Support\Money::format(\App\Support\Pricing::for('ppr', 'standard')) }}</span>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-gray-500">No matching provider reports.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $reports->links() }}</div>
</div>
