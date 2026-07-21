<?php

use App\Support\ImportDefaults;

it('derives label and folder from a YYYYMM prefix', function () {
    expect(ImportDefaults::fromFilename('202607-pir-index.xlsx'))
        ->toBe(['label' => 'July 2026', 'folder' => '2026-07']);
});

it('parses a bare date filename', function () {
    expect(ImportDefaults::fromFilename('202607.csv'))
        ->toBe(['label' => 'July 2026', 'folder' => '2026-07']);
});

it('handles january and december boundaries', function () {
    expect(ImportDefaults::fromFilename('202601_index.csv'))
        ->toBe(['label' => 'January 2026', 'folder' => '2026-01'])
        ->and(ImportDefaults::fromFilename('202512 index.xlsx'))
        ->toBe(['label' => 'December 2025', 'folder' => '2025-12']);
});

it('rejects invalid months', function () {
    expect(ImportDefaults::fromFilename('202613-pir-index.xlsx'))->toBeNull()
        ->and(ImportDefaults::fromFilename('202600-pir-index.xlsx'))->toBeNull();
});

it('rejects filenames without a leading six-digit prefix', function () {
    expect(ImportDefaults::fromFilename('pir-index-final.xlsx'))->toBeNull()
        ->and(ImportDefaults::fromFilename('2026-07-index.csv'))->toBeNull()
        ->and(ImportDefaults::fromFilename('20260.csv'))->toBeNull()
        ->and(ImportDefaults::fromFilename(''))->toBeNull();
});
