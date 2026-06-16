<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\FarIndexImporter;
use App\Support\FarIndexCsv;
use Illuminate\Console\Command;

class ImportFarIndex extends Command
{
    protected $signature = 'import:far-index {path : Path to the FAR index CSV} {label : Issue label, e.g. "2026 H1"}';

    protected $description = 'Import a FAR index CSV: upsert charities, FAR reports and the current issue.';

    public function handle(FarIndexImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = FarIndexCsv::read($path);

        $batch = ImportBatch::create([
            'label' => (string) $this->argument('label'),
            'type' => 'far_index',
            'status' => 'pending',
        ]);

        $importer->import($batch, $rows);
        $batch->refresh();

        $this->info("Imported '{$batch->label}': {$batch->rows} rows — {$batch->charities_created} charities created, {$batch->charities_updated} updated, {$batch->issues_created} issues created.");

        return self::SUCCESS;
    }
}
