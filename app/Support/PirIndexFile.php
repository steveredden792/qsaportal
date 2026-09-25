<?php

namespace App\Support;

class PirIndexFile
{
    /**
     * Read a PIR index (CSV or XLSX) into normalised rows.
     *
     * @return array<int, array{cc_ref:string,name:string,q_score:float|null,stability:float|null,q_grade:string|null,stability_grade:float|null,filename:string,accounting_date:string|null,charity_type:string|null,formation_date:string|null,stability_rank:int|null,q_score_rank:int|null,objectives:string|null}>
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
            'registerednumber' => 'cc_ref',
            'qscore' => 'q_score',
            'stability' => 'stability',
            'stabilityscore' => 'stability',
            'qgrade' => 'q_grade',
            'stabilitygrade' => 'stability_grade',
            'filename' => 'filename',
            'file' => 'filename',
            'pdffilename' => 'filename',
            'accountingdate' => 'accounting_date',
            'type' => 'charity_type',
            'yearofformation' => 'formation_date',
            'stabilityrank' => 'stability_rank',
            'qscorerank' => 'q_score_rank',
            'charityobjectives' => 'objectives',
        ];

        $dateFields = ['accounting_date', 'formation_date'];
        $rankFields = ['stability_rank', 'q_score_rank'];
        $numericFields = ['q_score', 'stability', 'stability_grade'];

        $rows = [];
        foreach (IndexRows::read($path) as $record) {
            $row = [
                'cc_ref' => '', 'name' => '', 'q_score' => null, 'stability' => null,
                'q_grade' => null, 'stability_grade' => null, 'filename' => '',
                'accounting_date' => null, 'charity_type' => null, 'formation_date' => null,
                'stability_rank' => null, 'q_score_rank' => null, 'objectives' => null,
            ];

            foreach ($record as $header => $value) {
                $key = $map[$header] ?? null;
                if ($key === null) {
                    continue;
                }

                $trimmed = trim((string) $value);

                if (in_array($key, $numericFields, true)) {
                    $row[$key] = $trimmed === '' ? null : (float) $trimmed;
                } elseif (in_array($key, $rankFields, true)) {
                    $trimmed = str_replace(',', '', $trimmed);
                    $row[$key] = $trimmed === '' ? null : (int) $trimmed;
                } elseif (in_array($key, $dateFields, true)) {
                    $date = $trimmed === '' ? false : \DateTime::createFromFormat('d/m/Y', $trimmed);
                    $row[$key] = $date === false ? null : $date->format('Y-m-d');
                } elseif ($key === 'q_grade' || $key === 'charity_type' || $key === 'objectives') {
                    $row[$key] = $trimmed === '' ? null : $trimmed;
                } else {
                    $row[$key] = $trimmed;
                }
            }

            // The index carries no PDF filename column; the published PDF is
            // named by the charity's CC reference, e.g. 1084866.pdf.
            if ($row['filename'] === '' && $row['cc_ref'] !== '') {
                $row['filename'] = $row['cc_ref'].'.pdf';
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
