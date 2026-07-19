<div>
    <h1 class="mb-6 text-2xl font-bold text-brand">My reports</h1>

    @if ($entitlements->isEmpty())
        <p class="text-gray-600">You haven't purchased any reports yet.
            <a href="{{ route('catalogue.pir') }}" class="text-brand hover:underline">Browse the PIR database</a>.</p>
    @else
        <div class="overflow-x-auto rounded border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="px-4 py-2">Report</th>
                        <th class="px-4 py-2">Issue</th>
                        <th class="px-4 py-2">Purchased</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($entitlements as $entitlement)
                        @php
                            $pdf = $entitlement->issue->assets->firstWhere('type', \App\Enums\AssetType::ReportPdf);
                            $status = $entitlement->status();
                        @endphp
                        <tr wire:key="ent-{{ $entitlement->id }}">
                            <td class="px-4 py-2 font-medium">{{ $entitlement->issue->report->name }}</td>
                            <td class="px-4 py-2">{{ $entitlement->issue->version_label }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $entitlement->created_at->format('j M Y') }}</td>
                            <td class="px-4 py-2">
                                @if ($status === 'active')
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                @elseif ($status === 'expiring')
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Expiring soon</span>
                                @else
                                    <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">Expired</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                @if ($entitlement->isActive() && $pdf)
                                    <a href="{{ route('assets.download', $pdf) }}" class="text-brand hover:underline">Download</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
