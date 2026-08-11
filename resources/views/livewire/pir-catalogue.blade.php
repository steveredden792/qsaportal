<div class="space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-slate-900">Public Information Reports</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">
                    Search charity records, compare Q scores and stability and open the report detail view for each entry.
                </p>
            </div>
            <div class="shrink-0 rounded-full border border-brand/20 bg-brand/5 px-4 py-2 text-sm font-medium text-brand">
                {{ $charities->total() }} reports available
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or CC number"
                   class="w-full rounded-full border-slate-300 bg-slate-50 px-4 py-2.5 focus:border-brand focus:ring-brand">
            <div class="grid grid-cols-2 gap-3">
                <input type="number" wire:model.live="qMin" placeholder="Q Score min" class="rounded-full border-slate-300 bg-slate-50 px-4 py-2.5 focus:border-brand focus:ring-brand">
                <input type="number" wire:model.live="qMax" placeholder="Q Score max" class="rounded-full border-slate-300 bg-slate-50 px-4 py-2.5 focus:border-brand focus:ring-brand">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <input type="number" wire:model.live="stabilityMin" placeholder="Stability min" class="rounded-full border-slate-300 bg-slate-50 px-4 py-2.5 focus:border-brand focus:ring-brand">
                <input type="number" wire:model.live="stabilityMax" placeholder="Stability max" class="rounded-full border-slate-300 bg-slate-50 px-4 py-2.5 focus:border-brand focus:ring-brand">
            </div>
        </div>
    </section>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="cursor-pointer px-4 py-3 font-semibold" wire:click="sortBy('name')">Charity</th>
                    <th class="px-4 py-3 font-semibold">CC ref</th>
                    <th class="cursor-pointer px-4 py-3 font-semibold" wire:click="sortBy('latest_q_score')">Q score</th>
                    <th class="cursor-pointer px-4 py-3 font-semibold" wire:click="sortBy('latest_stability')">Stability</th>
                    <th class="px-4 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($charities as $charity)
                    <tr wire:key="charity-{{ $charity->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $charity->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $charity->cc_ref }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $charity->latest_q_score }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $charity->latest_stability }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('reports.show', $charity->report->slug) }}"
                                   class="font-medium text-brand transition hover:text-brand-light">View report</a>
                                <livewire:add-to-basket :report="$charity->report" :key="'atb-'.$charity->id" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No reports match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $charities->links() }}</div>
</div>
