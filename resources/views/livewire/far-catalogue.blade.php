<div>
    <h1 class="mb-6 text-2xl font-bold text-brand">Financial Analysis Reports</h1>

    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-5">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search charity name or CC ref"
               class="md:col-span-2 rounded border-gray-300">
        <input type="number" wire:model.live="qMin" placeholder="Q min" class="rounded border-gray-300">
        <input type="number" wire:model.live="qMax" placeholder="Q max" class="rounded border-gray-300">
        <input type="number" wire:model.live="stabilityMin" placeholder="Stability min" class="rounded border-gray-300">
        <input type="number" wire:model.live="stabilityMax" placeholder="Stability max" class="rounded border-gray-300">
    </div>

    <div class="overflow-x-auto rounded border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="cursor-pointer px-4 py-2" wire:click="sortBy('name')">Charity</th>
                    <th class="px-4 py-2">CC ref</th>
                    <th class="cursor-pointer px-4 py-2" wire:click="sortBy('latest_q_score')">Q score</th>
                    <th class="cursor-pointer px-4 py-2" wire:click="sortBy('latest_stability')">Stability</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($charities as $charity)
                    <tr wire:key="charity-{{ $charity->id }}">
                        <td class="px-4 py-2 font-medium">{{ $charity->name }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $charity->cc_ref }}</td>
                        <td class="px-4 py-2">{{ $charity->latest_q_score }}</td>
                        <td class="px-4 py-2">{{ $charity->latest_stability }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('reports.show', $charity->report->slug) }}"
                               class="text-brand hover:underline">View report</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No reports match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $charities->links() }}</div>
</div>
