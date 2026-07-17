<?php

namespace App\Support;

use League\Csv\Reader;

class PirIndexCsv
{
    /**
     * Read a PIR index CSV into normalised rows keyed cc_ref/name/q_score/stability.
     * Header matching is case- and punctuation-insensitive.
     *
     * @return array<int, array{cc_ref:string,name:string,q_score:float|null,stability:float|null}>
     */
    public static function read(string $path): array
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $map = [
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

        $rows = [];
        foreach ($csv->getRecords() as $record) {
            $row = ['cc_ref' => '', 'name' => '', 'q_score' => null, 'stability' => null];

            foreach ($record as $header => $value) {
                $normalised = preg_replace('/[^a-z0-9]/', '', strtolower((string) $header));
                $key = $map[$normalised] ?? null;
                if ($key === null) {
                    continue;
                }

                if ($key === 'q_score' || $key === 'stability') {
                    $row[$key] = ($value === '' || $value === null) ? null : (float) $value;
                } else {
                    $row[$key] = trim((string) $value);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
