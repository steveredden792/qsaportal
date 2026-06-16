<?php

namespace App\Support;

use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Common\Entity\Row;

class FarIndexFile
{
    /**
     * Read a FAR index file (CSV or XLSX) into normalised rows.
     * Dispatches by extension: .csv → FarIndexCsv::read(); .xlsx → OpenSpout reader.
     *
     * @return array<int, array{cc_ref:string,name:string,q_score:float|null,stability:float|null}>
     */
    public static function read(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return FarIndexCsv::read($path);
        }

        return self::readXlsx($path);
    }

    /**
     * @return array<int, array{cc_ref:string,name:string,q_score:float|null,stability:float|null}>
     */
    private static function readXlsx(string $path): array
    {
        $map = self::headerMap();

        $reader = new XlsxReader();
        $reader->open($path);

        $rows = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                /** @var Row $row */
                $values = $row->toArray();

                if ($headers === null) {
                    // First row — treat as headers
                    $headers = array_map(
                        static fn (mixed $v): string => preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)),
                        $values
                    );
                    continue;
                }

                $record = array_combine($headers, array_pad(array_values($values), count($headers), null));
                $normalised = ['cc_ref' => '', 'name' => '', 'q_score' => null, 'stability' => null];

                foreach ($record as $normalisedHeader => $value) {
                    $key = $map[$normalisedHeader] ?? null;
                    if ($key === null) {
                        continue;
                    }

                    if ($key === 'q_score' || $key === 'stability') {
                        $normalised[$key] = ($value === '' || $value === null) ? null : (float) $value;
                    } else {
                        $normalised[$key] = trim((string) $value);
                    }
                }

                $rows[] = $normalised;
            }

            // Only read the first sheet
            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * Shared header normalisation map (same as FarIndexCsv).
     *
     * @return array<string, string>
     */
    private static function headerMap(): array
    {
        return [
            'charityname' => 'name',
            'charity' => 'name',
            'name' => 'name',
            'ccref' => 'cc_ref',
            'charitycommissionreference' => 'cc_ref',
            'charitycommissionref' => 'cc_ref',
            'regno' => 'cc_ref',
            'qscore' => 'q_score',
            'stability' => 'stability',
        ];
    }
}
