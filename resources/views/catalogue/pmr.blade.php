<x-public title="Provider Market Reports">
    <h1 class="mb-6 text-2xl font-bold text-brand">Provider Market Reports</h1>
    <div class="divide-y divide-gray-100 rounded border border-gray-200 bg-white">
        @forelse ($reports as $report)
            <div class="flex items-center justify-between px-4 py-3">
                <a href="{{ route('reports.show', $report->slug) }}" class="font-medium text-brand hover:underline">
                    {{ $report->market->name }}
                </a>
                <span class="text-sm text-gray-600">from {{ \App\Support\Money::format(\App\Support\Pricing::for('pmr', 'standard')) }}</span>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-gray-500">No market reports yet.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $reports->links() }}</div>
</x-public>
