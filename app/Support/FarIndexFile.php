<?php

namespace App\Support;

class FarIndexFile
{
    /**
     * Read a FAR index (CSV or XLSX) into normalised rows.
     * Related CC refs are a delimiter-separated list (`,` or `;`) —
     * format assumed pending third-party confirmation (spec §9.1).
     *
     * @return array<int, array{provider_ref:string,name:string,tier:string,filename:string,related_cc_refs:array<int,string>}>
     */
    public static function read(string $path): array
    {
        $map = [
            'providerref' => 'provider_ref',
            'providerreference' => 'provider_ref',
            'ref' => 'provider_ref',
            'code' => 'provider_ref',
            'providername' => 'name',
            'provider' => 'name',
            'name' => 'name',
            'tier' => 'tier',
            'filename' => 'filename',
            'file' => 'filename',
            'pdffilename' => 'filename',
            'relatedccrefs' => 'related_cc_refs',
            'relatedccref' => 'related_cc_refs',
            'relatedcharities' => 'related_cc_refs',
            'ccrefs' => 'related_cc_refs',
        ];

        $rows = [];
        foreach (IndexRows::read($path) as $record) {
            $row = ['provider_ref' => '', 'name' => '', 'tier' => '', 'filename' => '', 'related_cc_refs' => []];

            foreach ($record as $header => $value) {
                $key = $map[$header] ?? null;
                if ($key === null) {
                    continue;
                }

                if ($key === 'related_cc_refs') {
                    $row[$key] = array_values(array_filter(array_map('trim', preg_split('/[;,]/', (string) $value))));
                } elseif ($key === 'tier') {
                    $row[$key] = strtolower(trim((string) $value));
                } else {
                    $row[$key] = trim((string) $value);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
