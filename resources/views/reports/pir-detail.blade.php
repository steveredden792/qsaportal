<x-public :title="$charity->name" subtitle="Public Information Report">
    @php
        $fmtScore = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%';
        $fmtGrade = fn ($v) => $v === null || $v === '' ? '—' : (is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 1), '0'), '.') : $v);
        $fmtRank  = fn ($v) => $v === null ? '—' : number_format($v).' of '.number_format($rankTotal);
        $period   = $issue?->accounting_date
            ? ($issue->accounting_date->year - 1).'-'.$issue->accounting_date->year
            : $issue?->version_label;
    @endphp

    <style>
        .pir-back { display: inline-block; font-size: 0.85rem; font-weight: 500; color: #002842; text-decoration: none; }
        .pir-back:hover { color: #00c7c3; }
        .pir-layout { display: grid; gap: 2rem; margin-top: 1rem; }
        @media (min-width: 1024px) { .pir-layout { grid-template-columns: minmax(0, 1fr) 260px; gap: 3rem; } }
        .pir-doc { border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #fff; padding: 2rem; box-shadow: 0 1px 2px rgba(15,23,42,0.05); }
        @media (max-width: 640px) { .pir-doc { padding: 1.25rem; } }
        .pir-doc h1, .pir-doc h2, .pir-doc p { margin: 0; }
        .pir-title { background: #002842; color: #fff; text-align: center; padding: 1.4rem 1rem 1.2rem; border-radius: 0.75rem 0.75rem 0 0; }
        .pir-title h1 { font-family: 'Figtree', sans-serif; font-size: 1.9rem; font-weight: 700; line-height: 1.15; }
        .pir-title p { margin-top: 0.4rem; font-size: 1.05rem; letter-spacing: 0.04em; text-transform: uppercase; color: #cfd8e3; }
        .pir-table-wrap { overflow-x: auto; }
        .pir-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; line-height: 1.3; table-layout: fixed; }
        .pir-table th, .pir-table td { border: 2px solid #fff; padding: 0.45rem 0.5rem; text-align: left; vertical-align: top; }
        .pir-table thead th { background: #002842; color: #fff; font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.02em; }
        .pir-table thead th.pir-th-teal { background: #00b3b0; }
        .pir-table tbody td { background: #b8c4cc; color: #1e293b; font-weight: 600; }
        .pir-table tbody td.pir-td-teal { background: #7fd9d7; }
        .pir-table tbody td.pir-td-teal-label { background: #7fd9d7; font-weight: 700; }
        .pir-table tbody td.pir-td-head { background: #b8c4cc; }
        .pir-section { margin-top: 2.25rem; }
        .pir-section-tab { display: inline-block; background: #002842; color: #fff; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 0.55rem 1.25rem; border-radius: 0.6rem 0.6rem 0 0; }
        .pir-section-rule { border-bottom: 2px solid #002842; }
        .pir-doc p.pir-objects { margin-top: 30px; font-size: 0.8rem; line-height: 1.5; color: #475569; white-space: pre-line; }
        .pir-side { display: flex; flex-direction: column; gap: 1.5rem; }
        .pir-side-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #002842; }
        .pir-side-value { margin-top: 0.3rem; font-size: 1.15rem; color: #002842; }
        .pir-side-price { font-size: 1.6rem; font-weight: 600; color: #002842; }
        .pir-buy a, .pir-buy button { display: inline-flex; align-items: center; justify-content: center; border-radius: 0.25rem !important; background: #002842 !important; color: #fff !important; font-size: 0.95rem !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: 0.03em; padding: 0.7rem 1.4rem !important; text-decoration: none; border: 0 !important; transition: background-color 0.2s ease; }
        .pir-buy a:hover, .pir-buy button:hover { background: #00c7c3 !important; }
        .pir-owned { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; font-size: 0.8rem; color: #475569; }
        .pir-owned h2 { margin: 0 0 0.6rem; font-size: 0.85rem; font-weight: 700; color: #002842; }
        .pir-owned li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem; padding: 0.4rem 0; border-top: 1px solid #f1f5f9; }
        .pir-owned a { color: #002842; font-weight: 600; text-decoration: none; }
        .pir-owned a:hover { color: #00c7c3; }
    </style>

    <a href="{{ route('catalogue.pir') }}" class="pir-back">&larr; Back to PIR database</a>

    <div class="pir-layout">
        {{-- Report preview --}}
        <article class="pir-doc">
            <div class="pir-title">
                <h1>{{ $charity->name }}</h1>
                @if ($period)
                    <p>Annual Report {{ $period }}</p>
                @endif
            </div>

            <div class="pir-table-wrap">
                <table class="pir-table">
                    <colgroup>
                        <col style="width: 17%"><col style="width: 15%"><col style="width: 12%"><col style="width: 14%">
                        <col style="width: 10%"><col style="width: 11%"><col style="width: 10%"><col style="width: 11%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Charity Commission ref</th>
                            <th>Accounting date</th>
                            <th>Type</th>
                            <th>Year of formation</th>
                            <th class="pir-th-teal" colspan="2">Stability</th>
                            <th class="pir-th-teal" colspan="2">Q Assessment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="pir-td-head" rowspan="3">{{ $charity->cc_ref }}</td>
                            <td class="pir-td-head" rowspan="3">{{ $issue?->accounting_date?->format('j M Y') ?? '—' }}</td>
                            <td class="pir-td-head" rowspan="3">{{ $issue?->charity_type ?? '—' }}</td>
                            <td class="pir-td-head" rowspan="3">{{ $issue?->formation_date?->format('Y') ?? '—' }}</td>
                            <td class="pir-td-teal-label">Grade</td>
                            <td class="pir-td-teal">{{ $fmtGrade($issue?->stability_grade ?? $charity->latest_stability_grade) }}</td>
                            <td class="pir-td-teal-label">Grade</td>
                            <td class="pir-td-teal">{{ $fmtGrade($issue?->q_grade ?? $charity->latest_q_grade) }}</td>
                        </tr>
                        <tr>
                            <td class="pir-td-teal-label">Score</td>
                            <td class="pir-td-teal">{{ $fmtScore($issue?->stability ?? $charity->latest_stability) }}</td>
                            <td class="pir-td-teal-label">Score</td>
                            <td class="pir-td-teal">{{ $fmtScore($issue?->q_score ?? $charity->latest_q_score) }}</td>
                        </tr>
                        <tr>
                            <td class="pir-td-teal-label">Rank</td>
                            <td class="pir-td-teal">{{ $fmtRank($issue?->stability_rank) }}</td>
                            <td class="pir-td-teal-label">Rank</td>
                            <td class="pir-td-teal">{{ $fmtRank($issue?->q_score_rank) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($issue?->objectives)
                <section class="pir-section">
                    <div class="pir-section-rule">
                        <span class="pir-section-tab">Charitable objects</span>
                    </div>
                    <p class="pir-objects">{{ $issue->objectives }}</p>
                </section>
            @endif
        </article>

        {{-- Sidebar --}}
        <aside class="pir-side">
            <div>
                <div class="pir-side-label">Report title</div>
                <div class="pir-side-value">{{ $charity->name }}</div>
            </div>

            @if ($issue)
                <div>
                    <div class="pir-side-label">Issue date</div>
                    <div class="pir-side-value">{{ $issue->version_label }}</div>
                </div>
            @endif

            <div>
                <div class="pir-side-label">Price</div>
                <div class="pir-side-price">{{ \App\Support\Money::format($price) }}</div>
            </div>

            <div class="pir-buy">
                <livewire:add-to-basket :report="$report" />
            </div>

            @auth
                @if ($teaser)
                    <div>
                        <a href="{{ route('assets.download', $teaser) }}" class="pir-back">View free sample</a>
                    </div>
                @endif
            @endauth

            @if ($ownedEntitlements->isNotEmpty())
                <div class="pir-owned">
                    <h2>Your purchased issues</h2>
                    <ul>
                        @foreach ($ownedEntitlements as $entitlement)
                            @php $pdf = $entitlement->issue->assets->firstWhere('type', \App\Enums\AssetType::ReportPdf); @endphp
                            <li>
                                <span>{{ $entitlement->issue->version_label }}</span>
                                <span>
                                    @if ($pdf)
                                        <a href="{{ route('assets.download', $pdf) }}">Download</a>
                                    @endif
                                    <span style="color:#94a3b8"> (expires {{ $entitlement->expires_at->format('j M Y') }})</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-public>
