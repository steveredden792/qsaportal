<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\ReportType;
use App\Models\Asset;
use App\Models\Charity;
use App\Models\FarPirReference;
use App\Models\ImportBatch;
use App\Models\Issue;
use App\Models\Provider;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FarIndexImporter
{
    private const S3_PREFIX = 'far';

    /**
     * Validate then publish a FAR index. All-or-nothing: any row error
     * fails the batch and nothing is written.
     *
     * @param  iterable<array{provider_ref:string,name:string,tier:string,filename:string,related_cc_refs:array<int,string>}>  $rows
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
                $ref = trim((string) $row['provider_ref']);

                $provider = Provider::where('code', $ref)->first();
                if ($provider) {
                    $provider->update(['name' => $row['name']]);
                    $updated++;
                } else {
                    $provider = Provider::create(['code' => $ref, 'name' => $row['name']]);
                    $created++;
                }

                $report = Report::firstOrCreate(
                    ['type' => ReportType::FAR, 'provider_id' => $provider->id],
                    ['name' => $provider->name.' — Financial Analysis Report', 'slug' => 'far-'.Str::slug($ref)],
                );
                $report->update(['tier' => $row['tier']]);

                $issue = Issue::where('report_id', $report->id)
                    ->where('version_label', $batch->label)
                    ->first();

                if (! $issue) {
                    Issue::where('report_id', $report->id)->update(['is_current' => false]);
                    $issue = Issue::create([
                        'report_id' => $report->id,
                        'version_label' => $batch->label,
                        'published_at' => now(),
                        'is_current' => true,
                    ]);
                    $issuesCreated++;
                }

                Asset::updateOrCreate(
                    ['issue_id' => $issue->id, 'type' => AssetType::ReportPdf],
                    [
                        'disk' => 's3',
                        'path' => $this->s3Path($batch, $row['filename']),
                        'original_filename' => $row['filename'],
                        'mime' => 'application/pdf',
                    ],
                );

                $charityIds = Charity::whereIn('cc_ref', $row['related_cc_refs'])->pluck('id', 'cc_ref');
                foreach ($row['related_cc_refs'] as $ccRef) {
                    FarPirReference::firstOrCreate([
                        'issue_id' => $issue->id,
                        'charity_id' => $charityIds[$ccRef],
                    ]);
                }
            }
        });

        $batch->update([
            'status' => 'published',
            'rows' => count($rows),
            'providers_created' => $created,
            'providers_updated' => $updated,
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
        $tiers = config('reports.far_tiers');
        $errors = [];
        $seen = [];

        foreach (array_values($rows) as $i => $row) {
            $line = $i + 1;
            $ref = trim((string) ($row['provider_ref'] ?? ''));
            $filename = trim((string) ($row['filename'] ?? ''));
            $tier = (string) ($row['tier'] ?? '');

            if ($ref === '') {
                $errors[] = ['row' => $line, 'error' => 'Missing provider ref'];
            } elseif (isset($seen[$ref])) {
                $errors[] = ['row' => $line, 'error' => "Duplicate provider ref {$ref} (first seen row {$seen[$ref]})"];
            } else {
                $seen[$ref] = $line;
            }

            if (trim((string) ($row['name'] ?? '')) === '') {
                $errors[] = ['row' => $line, 'error' => 'Missing provider name'];
            }

            if (! in_array($tier, $tiers, true)) {
                $errors[] = ['row' => $line, 'error' => "Unknown tier '{$tier}' (allowed: ".implode(', ', $tiers).')'];
            }

            if ($filename === '') {
                $errors[] = ['row' => $line, 'error' => 'Missing filename'];
            } elseif (config('reports.validate_import_files') && ! Storage::disk('s3')->exists($this->s3Path($batch, $filename))) {
                $errors[] = ['row' => $line, 'error' => "File not found on S3: {$this->s3Path($batch, $filename)}"];
            }

            $known = Charity::whereIn('cc_ref', $row['related_cc_refs'] ?? [])->pluck('cc_ref')->all();
            foreach (array_diff($row['related_cc_refs'] ?? [], $known) as $unknown) {
                $errors[] = ['row' => $line, 'error' => "Unknown related CC ref {$unknown} — import the PIR index first"];
            }
        }

        return $errors;
    }

    private function s3Path(ImportBatch $batch, string $filename): string
    {
        return self::S3_PREFIX.'/'.$batch->folder.'/'.$filename;
    }
}
