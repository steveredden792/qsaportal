<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand">Access</p>
                <h1 class="mt-2 text-3xl font-semibold text-slate-900">My reports</h1>
                <p class="mt-2 text-sm text-slate-600">View your purchased reports, status and available downloads.</p>
            </div>
            <a href="{{ route('catalogue.pir') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-brand hover:text-brand">Browse more reports</a>
        </div>
    </div>

    @if ($entitlements->isEmpty())
        <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-slate-600">You haven't purchased any reports yet.</p>
            <a href="{{ route('catalogue.pir') }}" class="mt-4 inline-flex text-brand transition hover:text-brand-light">Browse the PIR database</a>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Report</th>
                        <th class="px-4 py-3 font-semibold">Issue</th>
                        <th class="px-4 py-3 font-semibold">Purchased</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($entitlements as $entitlement)
                        @php
                            $pdf = $entitlement->issue->assets->firstWhere('type', \App\Enums\AssetType::ReportPdf);
                            $status = $entitlement->status();
                        @endphp
                        <tr wire:key="ent-{{ $entitlement->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $entitlement->issue->report->name }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $entitlement->issue->version_label }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $entitlement->created_at->format('j M Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($status === 'active')
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                @elseif ($status === 'expiring')
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Expiring soon</span>
                                @else
                                    <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">Expired</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($entitlement->isActive() && $pdf)
                                    <a href="{{ route('assets.download', $pdf) }}" class="font-medium text-brand transition hover:text-brand-light">Download</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
