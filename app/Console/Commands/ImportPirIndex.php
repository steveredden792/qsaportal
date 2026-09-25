<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Support\ImportDefaults;
use App\Services\PirIndexImporter;
use App\Support\PirIndexFile;
use Illuminate\Console\Command;

class ImportPirIndex extends Command
{
    protected $signature = 'import:pir-index {path : Path to the PIR index CSV/XLSX} {label? : Issue label, e.g. "June 2026" (defaults from a YYYY-MM filename)} {folder? : S3 publication folder, e.g. 2026-06 (defaults from a YYYY-MM filename)}';

    protected $description = 'Validate and publish a PIR index: upsert charities, PIR reports, current issues and PDF assets.';

    public function handle(PirIndexImporter $importer): int
    {
        $path = (string) $this->argument('path');

        // Relative paths are resolved against the project root as well as the
        // current directory, so the command works from 20i's Command Executor.
        if (! is_file($path) && is_file(base_path($path))) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $defaults = ImportDefaults::fromFilename(basename($path));
        $label = (string) ($this->argument('label') ?? $defaults['label'] ?? '');
        $folder = (string) ($this->argument('folder') ?? $defaults['folder'] ?? '');

        if ($label === '' || $folder === '') {
            $this->error('Label and folder are required when the filename does not start with YYYY-MM (e.g. 2026-06-pir-index.csv).');

            return self::FAILURE;
        }

        $this->info("Importing {$path} as '{$label}' from S3 folder '{$folder}'…");

        $batch = ImportBatch::create([
            'label' => $label,
            'type' => 'pir_index',
            'folder' => $folder,
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
