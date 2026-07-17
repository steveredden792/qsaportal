<?php

namespace App\Filament\Pages;

use App\Models\ImportBatch;
use App\Services\PirIndexImporter;
use App\Support\PirIndexFile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class ImportPirIndex extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Import PIR index';

    protected static string | UnitEnum | null $navigationGroup = 'Data';

    protected static ?string $title = 'Import PIR Index';

    protected string $view = 'filament.pages.import-pir-index';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Upload & Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalHeading('Import PIR Index File')
                ->modalDescription('Upload a .csv or .xlsx PIR index file and enter the issue label.')
                ->modalSubmitActionLabel('Import')
                ->form([
                    FileUpload::make('file')
                        ->label('PIR Index File (.csv or .xlsx)')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->storeFiles(false)
                        ->required(),
                    TextInput::make('label')
                        ->label('Issue Label')
                        ->placeholder('e.g. 2026 H1')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data, PirIndexImporter $importer): void {
                    /** @var TemporaryUploadedFile $file */
                    $file = $data['file'];
                    $path = $file->getRealPath();

                    $rows = PirIndexFile::read($path);

                    $batch = ImportBatch::create([
                        'label' => $data['label'],
                        'type' => 'pir_index',
                        'status' => 'pending',
                    ]);

                    $importer->import($batch, $rows);
                    $batch->refresh();

                    Notification::make()
                        ->success()
                        ->title("Import complete: {$batch->label}")
                        ->body(
                            "{$batch->rows} rows — " .
                            "{$batch->charities_created} charities created, " .
                            "{$batch->charities_updated} updated, " .
                            "{$batch->issues_created} issues created."
                        )
                        ->send();
                }),
        ];
    }

    /**
     * Run the import directly from a file path and label (used in tests
     * and CLI tooling to bypass the Livewire upload mechanism).
     */
    public function runImport(string $path, string $label): ImportBatch
    {
        $rows = PirIndexFile::read($path);

        $batch = ImportBatch::create([
            'label' => $label,
            'type' => 'pir_index',
            'status' => 'pending',
        ]);

        $importer = app(PirIndexImporter::class);
        $importer->import($batch, $rows);
        $batch->refresh();

        return $batch;
    }
}
