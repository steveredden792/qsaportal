<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\FarIndexImporter;
use App\Support\FarIndexFile;
use Illuminate\Console\Command;

class ImportFarIndex extends Command
{
    protected $signature = 'import:far-index {path : Path to the FAR index CSV/XLSX} {label : Issue label, e.g. "2026 H2"} {folder : S3 publication folder, e.g. 2026-07}';

    protected $description = 'Validate and publish a FAR index: upsert providers, tiered FAR reports, current issues, PDF assets and related-PIR references.';

    public function handle(FarIndexImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $batch = ImportBatch::create([
            'label' => (string) $this->argument('label'),
            'type' => 'far_index',
            'folder' => (string) $this->argument('folder'),
        ]);

        $importer->import($batch, FarIndexFile::read($path));
        $batch->refresh();

        if ($batch->status === 'failed') {
            $this->error("Validation failed for '{$batch->label}' — nothing imported:");
            foreach ($batch->errors as $error) {
                $this->line("  row {$error['row']}: {$error['error']}");
            }

            return self::FAILURE;
        }

        $this->info("Published '{$batch->label}': {$batch->rows} rows — {$batch->providers_created} providers created, {$batch->providers_updated} updated, {$batch->issues_created} issues created.");

        return self::SUCCESS;
    }
}
