<?php

namespace Database\Seeders;

use App\Enums\AssetType;
use App\Enums\ReportType;
use App\Models\Asset;
use App\Models\Issue;
use Illuminate\Database\Seeder;

/**
 * Demo helper: give every current PIR issue a downloadable report_pdf asset
 * (pointing at config('demo.sample_report_pdf')) so the purchase -> download
 * flow is exercisable on existing seeded data. Idempotent; safe to re-run.
 */
class DemoReportPdfBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $path = config('demo.sample_report_pdf');

        Issue::query()
            ->where('is_current', true)
            ->whereHas('report', fn ($q) => $q->where('type', ReportType::PIR))
            ->each(function (Issue $issue) use ($path): void {
                Asset::updateOrCreate(
                    ['issue_id' => $issue->id, 'type' => AssetType::ReportPdf],
                    [
                        'disk' => config('demo.sample_report_disk'),
                        'path' => $path,
                        'original_filename' => 'sample-report.pdf',
                        'mime' => 'application/pdf',
                    ],
                );
            });
    }
}
