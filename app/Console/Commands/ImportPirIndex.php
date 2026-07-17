<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\PirIndexImporter;
use App\Support\PirIndexFile;
use Illuminate\Console\Command;

class ImportPirIndex extends Command
{
    protected $signature = 'import:pir-index {path : Path to the PIR index CSV} {label : Issue label, e.g. "2026 H1"}';

    protected $description = 'Import a PIR index CSV: upsert charities, PIR reports and the current issue.';

    public function handle(PirIndexImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = PirIndexFile::read($path);

        $batch = ImportBatch::create([
            'label' => (string) $this->argument('label'),
            'type' => 'pir_index',
            'status' => 'pending',
        ]);

        $importer->import($batch, $rows);
        $batch->refresh();

        $this->info("Imported '{$batch->label}': {$batch->rows} rows — {$batch->charities_created} charities created, {$batch->charities_updated} updated, {$batch->issues_created} issues created.");

        return self::SUCCESS;
    }
}
