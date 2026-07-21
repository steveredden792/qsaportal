<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\ReportType;
use App\Models\Asset;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\Issue;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PirIndexImporter
{
    /**
     * Validate then publish a PIR index. All-or-nothing: any row error
     * fails the batch and nothing is written.
     *
     * @param  iterable<array{cc_ref:string,name:string,q_score:float|null,stability:float|null,filename:string}>  $rows
     */
    public function import(ImportBatch $batch, iterable $rows): ImportBatch
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows);

        $errors = $this->validate($batch, $rows);
        if ($errors !== []) {
            $batch->update(['status' => 'failed', 'errors' => $errors, 'rows' => count($rows)]);

            return $batch;
        }

        $created = 0;
        $updated = 0;
        $issuesCreated = 0;

        DB::transaction(function () use ($batch, $rows, &$created, &$updated, &$issuesCreated) {
            foreach ($rows as $row) {
                $ccRef = trim((string) $row['cc_ref']);

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

                $issue = Issue::where('report_id', $report->id)
                    ->where('version_label', $batch->label)
                    ->first();

                if ($issue) {
                    $issue->update(['q_score' => $row['q_score'], 'stability' => $row['stability']]);
                } else {
                    Issue::where('report_id', $report->id)->update(['is_current' => false]);
                    $issue = Issue::create([
                        'report_id' => $report->id,
                        'version_label' => $batch->label,
                        'published_at' => now(),
                        'is_current' => true,
                        'q_score' => $row['q_score'],
                        'stability' => $row['stability'],
                    ]);
                    $issuesCreated++;
                }

                Asset::updateOrCreate(
                    ['issue_id' => $issue->id, 'type' => AssetType::ReportPdf],
                    [
                        'disk' => 's3',
                        'path' => $batch->folder.'/'.$row['filename'],
                        'original_filename' => $row['filename'],
                        'mime' => 'application/pdf',
                    ],
                );
            }
        });

        $batch->update([
            'status' => 'published',
            'rows' => count($rows),
            'charities_created' => $created,
            'charities_updated' => $updated,
            'issues_created' => $issuesCreated,
        ]);

        return $batch;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{row:int, error:string}>
     */
    private function validate(ImportBatch $batch, array $rows): array
    {
        $errors = [];
        $seen = [];

        foreach (array_values($rows) as $i => $row) {
            $line = $i + 1; // 1-indexed data row
            $ccRef = trim((string) ($row['cc_ref'] ?? ''));
            $filename = trim((string) ($row['filename'] ?? ''));

            if ($ccRef === '') {
                $errors[] = ['row' => $line, 'error' => 'Missing CC ref'];
            } elseif (isset($seen[$ccRef])) {
                $errors[] = ['row' => $line, 'error' => "Duplicate CC ref {$ccRef} (first seen row {$seen[$ccRef]})"];
            } else {
                $seen[$ccRef] = $line;
            }

            if (trim((string) ($row['name'] ?? '')) === '') {
                $errors[] = ['row' => $line, 'error' => 'Missing charity name'];
            }

            if ($filename === '') {
                $errors[] = ['row' => $line, 'error' => 'Missing filename'];
            } elseif (config('reports.validate_import_files') && ! Storage::disk('s3')->exists($batch->folder.'/'.$filename)) {
                $errors[] = ['row' => $line, 'error' => "File not found on S3: {$batch->folder}/{$filename}"];
            }
        }

        return $errors;
    }
}
