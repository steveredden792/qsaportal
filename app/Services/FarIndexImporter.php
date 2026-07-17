<?php

namespace App\Services;

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\Issue;
use App\Models\Report;
use Illuminate\Support\Facades\DB;

class FarIndexImporter
{
    /**
     * Upsert charities, FAR reports and a current issue from normalised index rows.
     *
     * @param  iterable<array{cc_ref:string,name:string,q_score:float|null,stability:float|null}>  $rows
     */
    public function import(ImportBatch $batch, iterable $rows): ImportBatch
    {
        $rowCount = 0;
        $created = 0;
        $updated = 0;
        $issuesCreated = 0;

        DB::transaction(function () use ($batch, $rows, &$rowCount, &$created, &$updated, &$issuesCreated) {
            foreach ($rows as $row) {
                $ccRef = trim((string) $row['cc_ref']);
                if ($ccRef === '') {
                    continue;
                }
                $rowCount++;

                $charity = Charity::where('cc_ref', $ccRef)->first();
                if ($charity) {
                    $charity->update([
                        'name' => $row['name'],
                        'latest_q_score' => $row['q_score'],
                        'latest_stability' => $row['stability'],
                    ]);
                    $updated++;
                } else {
                    $charity = Charity::create([
                        'cc_ref' => $ccRef,
                        'name' => $row['name'],
                        'latest_q_score' => $row['q_score'],
                        'latest_stability' => $row['stability'],
                    ]);
                    $created++;
                }

                $report = Report::firstOrCreate(
                    ['type' => ReportType::PIR, 'charity_id' => $charity->id],
                    ['name' => $charity->name.' — Public Information Report', 'slug' => 'pir-'.$ccRef],
                );

                $existing = Issue::where('report_id', $report->id)
                    ->where('version_label', $batch->label)
                    ->first();

                if ($existing) {
                    $existing->update(['q_score' => $row['q_score'], 'stability' => $row['stability']]);
                } else {
                    Issue::where('report_id', $report->id)->update(['is_current' => false]);
                    Issue::create([
                        'report_id' => $report->id,
                        'version_label' => $batch->label,
                        'published_at' => now(),
                        'is_current' => true,
                        'q_score' => $row['q_score'],
                        'stability' => $row['stability'],
                    ]);
                    $issuesCreated++;
                }
            }
        });

        $batch->update([
            'status' => 'completed',
            'rows' => $rowCount,
            'charities_created' => $created,
            'charities_updated' => $updated,
            'issues_created' => $issuesCreated,
        ]);

        return $batch;
    }
}
