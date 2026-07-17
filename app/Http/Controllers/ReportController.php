<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Enums\ReportType;
use App\Models\Report;
use App\Support\Pricing;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function show(Report $report): View
    {
        $report->load('charity', 'currentIssue.assets');
        $teaser = $report->currentIssue?->assets->firstWhere('type', AssetType::Teaser);

        if ($report->type !== ReportType::PIR) {
            abort(404);
        }

        return view('reports.far-detail', [
            'report' => $report,
            'charity' => $report->charity,
            'issue' => $report->currentIssue,
            'teaser' => $teaser,
            'price' => Pricing::for('far', 'single'),
        ]);
    }
}
