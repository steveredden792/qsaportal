<?php

use App\Support\FarIndexCsv;

it('reads and normalises a FAR index csv', function () {
    $rows = FarIndexCsv::read(base_path('tests/fixtures/far-index-sample.csv'));

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
