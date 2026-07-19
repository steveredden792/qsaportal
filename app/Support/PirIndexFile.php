<?php

namespace App\Support;

class PirIndexFile
{
    /**
     * Read a PIR index (CSV or XLSX) into normalised rows.
     *
     * @return array<int, array{cc_ref:string,name:string,q_score:float|null,stability:float|null,filename:string}>
     */
    public static function read(string $path): array
    {
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
            'filename' => 'filename',
            'file' => 'filename',
            'pdffilename' => 'filename',
        ];

        $rows = [];
        foreach (IndexRows::read($path) as $record) {
            $row = ['cc_ref' => '', 'name' => '', 'q_score' => null, 'stability' => null, 'filename' => ''];

            foreach ($record as $header => $value) {
                $key = $map[$header] ?? null;
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
