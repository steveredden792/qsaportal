<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Enums\ReportType;
use App\Models\Report;
use App\Support\Pricing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function show(Request $request, Report $report): View
    {
        $report->load('charity', 'currentIssue.assets');
        $teaser = $report->currentIssue?->assets->firstWhere('type', AssetType::Teaser);

        if ($report->type !== ReportType::PIR) {
            abort(404);
        }

        $ownedEntitlements = $request->user()->entitlements()
            ->active()
            ->whereHas('issue', fn ($q) => $q->where('report_id', $report->id))
            ->with('issue.assets')
            ->get();

        return view('reports.pir-detail', [
            'report' => $report,
            'charity' => $report->charity,
            'issue' => $report->currentIssue,
            'teaser' => $teaser,
            'price' => Pricing::for('pir', 'single'),
            'ownedEntitlements' => $ownedEntitlements,
        ]);
    }
}
