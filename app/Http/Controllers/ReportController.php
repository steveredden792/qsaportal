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
        $report->load('charity', 'provider', 'market', 'currentIssue.assets');
        $teaser = $report->currentIssue?->assets->firstWhere('type', AssetType::Teaser);

        return match ($report->type) {
            ReportType::FAR => view('reports.far-detail', [
                'report' => $report,
                'charity' => $report->charity,
                'issue' => $report->currentIssue,
                'teaser' => $teaser,
                'price' => Pricing::for('far', 'single'),
            ]),
            ReportType::PPR => view('reports.ppr-detail', [
                'report' => $report,
                'provider' => $report->provider,
                'issue' => $report->currentIssue,
                'teaser' => $teaser,
                'tiers' => [
                    ['name' => 'Standard', 'price' => Pricing::for('ppr', 'standard'), 'desc' => 'Named-provider report.'],
                    ['name' => 'Enhanced', 'price' => Pricing::for('ppr', 'enhanced'), 'desc' => 'Report + linked charity relationship dataset.'],
                    ['name' => 'Premium', 'price' => Pricing::for('ppr', 'premium'), 'desc' => 'Report + dataset + time-boxed FAR access to linked charities.'],
                ],
            ]),
            ReportType::PMR => view('reports.pmr-detail', [
                'report' => $report,
                'market' => $report->market,
                'issue' => $report->currentIssue,
                'teaser' => $teaser,
                'tiers' => [
                    ['name' => 'Standard', 'price' => Pricing::for('pmr', 'standard'), 'desc' => 'Category report.'],
                    ['name' => 'Premium', 'price' => Pricing::for('pmr', 'premium'), 'desc' => 'Category report + supporting data and defined FAR access.'],
                ],
            ]),
        };
    }
}
