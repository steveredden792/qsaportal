<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ImportDefaults
{
    /**
     * Derive Label + S3-folder defaults from a YYYYMM-prefixed index
     * filename (e.g. "202607-pir-index.xlsx" → July 2026 / 2026-07).
     * Null when the first six characters are not a valid YYYYMM.
     *
     * @return array{label: string, folder: string}|null
     */
    public static function fromFilename(string $filename): ?array
    {
        if (! preg_match('/^(\d{4})(\d{2})/', $filename, $matches)) {
            return null;
        }

        $month = (int) $matches[2];

        if ($month < 1 || $month > 12) {
            return null;
        }

        $date = CarbonImmutable::createFromDate((int) $matches[1], $month, 1);

        return [
            'label' => $date->format('F Y'),
            'folder' => $date->format('Y-m'),
        ];
    }
}
