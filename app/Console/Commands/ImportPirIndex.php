<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\PirIndexImporter;
use App\Support\PirIndexFile;
use Illuminate\Console\Command;

class ImportPirIndex extends Command
{
    protected $signature = 'import:pir-index {path : Path to the PIR index CSV/XLSX} {label : Issue label, e.g. "2026 H2"} {folder : S3 publication folder, e.g. 2026-07}';

    protected $description = 'Validate and publish a PIR index: upsert charities, PIR reports, current issues and PDF assets.';

    public function handle(PirIndexImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $batch = ImportBatch::create([
            'label' => (string) $this->argument('label'),
            'type' => 'pir_index',
            'folder' => (string) $this->argument('folder'),
        ]);

        $importer->import($batch, PirIndexFile::read($path));
        $batch->refresh();

        if ($batch->status === 'failed') {
            $this->error("Validation failed for '{$batch->label}' — nothing imported:");
            foreach ($batch->errors as $error) {
                $this->line("  row {$error['row']}: {$error['error']}");
            }

            return self::FAILURE;
        }

        $this->info("Published '{$batch->label}': {$batch->rows} rows — {$batch->charities_created} charities created, {$batch->charities_updated} updated, {$batch->issues_created} issues created.");

        return self::SUCCESS;
    }
}
