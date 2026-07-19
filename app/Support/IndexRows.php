<?php

namespace App\Support;

use League\Csv\Reader as CsvReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class IndexRows
{
    /**
     * Read a CSV or XLSX into records keyed by normalised headers
     * (lowercase, punctuation stripped). First sheet only for XLSX.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function read(string $path): array
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv'
            ? self::readCsv($path)
            : self::readXlsx($path);
    }

    private static function normalise(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($header));
    }

    /** @return array<int, array<string, mixed>> */
    private static function readCsv(string $path): array
    {
        $csv = CsvReader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $records = [];
        foreach ($csv->getRecords() as $record) {
            $row = [];
            foreach ($record as $header => $value) {
                $row[self::normalise((string) $header)] = $value;
            }
            $records[] = $row;
        }

        return $records;
    }

    /** @return array<int, array<string, mixed>> */
    private static function readXlsx(string $path): array
    {
        $reader = new XlsxReader();
        $reader->open($path);

        $records = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                /** @var Row $row */
                $values = $row->toArray();

                if ($headers === null) {
                    $headers = array_map(fn (mixed $v): string => self::normalise((string) $v), $values);
                    continue;
                }

                $records[] = array_combine($headers, array_pad(array_values($values), count($headers), null));
            }
            break; // first sheet only
        }

        $reader->close();

        return $records;
    }
}
