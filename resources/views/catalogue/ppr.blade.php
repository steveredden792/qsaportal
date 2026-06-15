<x-public title="Provider Portfolio Reports">
    <h1 class="mb-6 text-2xl font-bold text-brand">Provider Portfolio Reports</h1>

    <form method="GET" class="mb-4 flex gap-2">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search provider name"
               class="rounded border-gray-300">
        <button type="submit" class="rounded bg-brand px-4 py-2 text-sm font-medium text-white">Search</button>
        @if ($search !== '')
            <a href="{{ route('catalogue.ppr') }}" class="px-3 py-2 text-sm text-gray-500 hover:underline">Clear</a>
        @endif
    </form>

    <div class="divide-y divide-gray-100 rounded border border-gray-200 bg-white">
        @forelse ($reports as $report)
            <div class="flex items-center justify-between px-4 py-3">
                <a href="{{ route('reports.show', $report->slug) }}" class="font-medium text-brand hover:underline">
                    {{ $report->provider->name }}
                </a>
                <span class="text-sm text-gray-600">from {{ \App\Support\Money::format(\App\Support\Pricing::for('ppr', 'standard')) }}</span>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-gray-500">No provider reports yet.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $reports->links() }}</div>
</x-public>
