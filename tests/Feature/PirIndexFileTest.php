<?php

use App\Support\PirIndexFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

it('reads the CSV fixture via PirIndexFile', function () {
    $rows = PirIndexFile::read(base_path('tests/fixtures/pir-index-sample.csv'));

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toMatchArray([
        'cc_ref' => '1234567',
        'name' => 'Acme Trust',
        'q_score' => 55.5,
        'stability' => 60.0,
    ]);
    expect($rows[1]['cc_ref'])->toBe('7654321')
        ->and($rows[1]['q_score'])->toBe(42.1);
});

it('reads an XLSX file via PirIndexFile', function () {
    // Generate a small XLSX in a temp file
    $path = sys_get_temp_dir() . '/pir-index-test-' . uniqid() . '.xlsx';

    $writer = new XlsxWriter();
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Charity Name', 'CC Ref', 'Q Score', 'Stability']));
    $writer->addRow(Row::fromValues(['Acme Trust', '1234567', 55.5, 60.0]));
    $writer->addRow(Row::fromValues(['Beacon Foundation', '7654321', 42.1, 75.2]));
    $writer->close();

    $rows = PirIndexFile::read($path);

    @unlink($path);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toMatchArray([
        'cc_ref' => '1234567',
        'name' => 'Acme Trust',
        'q_score' => 55.5,
        'stability' => 60.0,
    ]);
    expect($rows[1])->toMatchArray([
        'cc_ref' => '7654321',
        'name' => 'Beacon Foundation',
        'q_score' => 42.1,
        'stability' => 75.2,
    ]);
});
