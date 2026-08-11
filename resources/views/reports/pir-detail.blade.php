<x-public :title="$charity->name" subtitle="Public Information Report">
    <div class="space-y-6">
        <div>
            <a href="{{ route('catalogue.pir') }}" class="text-sm font-medium text-brand transition hover:text-brand-light">&larr; Back to PIR database</a>
            <p class="mt-3 text-slate-600">Charity Commission ref: {{ $charity->cc_ref }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1.3fr_0.7fr]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Q score</div>
                        <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $charity->latest_q_score }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Stability</div>
                        <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $charity->latest_stability }}</div>
                    </div>
                </div>

                @if ($issue)
                    <p class="mt-6 text-sm text-slate-600">
                        Current issue: <span class="font-semibold text-slate-900">{{ $issue->version_label }}</span>
                        (published {{ $issue->published_at?->format('j M Y') }})
                    </p>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-200 bg-brand px-6 py-6 text-white shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-brand-light">Access</p>
                <div class="mt-3 flex items-center gap-4">
                    <span class="text-3xl font-semibold">{{ \App\Support\Money::format($price) }}</span>
                </div>
                <div class="mt-6">
                    <livewire:add-to-basket :report="$report" />
                </div>
            </section>
        </div>

        @if ($ownedEntitlements->isNotEmpty())
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Your purchased issues</h2>
                <ul class="mt-4 space-y-3 text-sm text-slate-600">
                    @foreach ($ownedEntitlements as $entitlement)
                        @php $pdf = $entitlement->issue->assets->firstWhere('type', \App\Enums\AssetType::ReportPdf); @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 px-4 py-3">
                            <span>{{ $entitlement->issue->version_label }}</span>
                            <div class="flex items-center gap-3">
                                @if ($pdf)
                                    <a href="{{ route('assets.download', $pdf) }}" class="font-medium text-brand transition hover:text-brand-light">Download</a>
                                @endif
                                <span class="text-slate-500">(expires {{ $entitlement->expires_at->format('j M Y') }})</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @auth
            @if ($teaser)
                <p>
                    <a href="{{ route('assets.download', $teaser) }}" class="text-sm font-medium text-brand transition hover:text-brand-light">View free sample</a>
                </p>
            @endif
        @endauth
    </div>
</x-public>
