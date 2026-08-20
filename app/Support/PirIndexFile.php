<?php

namespace App\Support;

class PirIndexFile
{
    /**
     * Read a PIR index (CSV or XLSX) into normalised rows.
     *
     * @return array<int, array{cc_ref:string,name:string,q_score:float|null,stability:float|null,q_grade:string|null,stability_grade:float|null,filename:string}>
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
            'qgrade' => 'q_grade',
            'stabilitygrade' => 'stability_grade',
            'filename' => 'filename',
            'file' => 'filename',
            'pdffilename' => 'filename',
        ];

        $rows = [];
        foreach (IndexRows::read($path) as $record) {
            $row = ['cc_ref' => '', 'name' => '', 'q_score' => null, 'stability' => null, 'q_grade' => null, 'stability_grade' => null, 'filename' => ''];

            foreach ($record as $header => $value) {
                $key = $map[$header] ?? null;
                if ($key === null) {
                    continue;
                }

                if ($key === 'q_score' || $key === 'stability' || $key === 'stability_grade') {
                    $row[$key] = ($value === '' || $value === null) ? null : (float) $value;
                } elseif ($key === 'q_grade') {
                    $trimmed = trim((string) $value);
                    $row[$key] = $trimmed === '' ? null : $trimmed;
                } else {
                    $row[$key] = trim((string) $value);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
