<?php

use App\Support\FarIndexFile;

it('reads a FAR index CSV with related cc refs', function () {
    $csv = tempnam(sys_get_temp_dir(), 'far').'.csv';
    file_put_contents($csv,
        "Provider Ref,Provider Name,Tier,Filename,Related CC Refs\n".
        "PRV-1000,Acme Care,premium,acme-prv-1000.pdf,\"1111111; 2222222,3333333\"\n".
        "PRV-2000,Beacon Health,standard,beacon-prv-2000.pdf,\n"
    );

    $rows = FarIndexFile::read($csv);

    @unlink($csv);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['provider_ref'])->toBe('PRV-1000')
        ->and($rows[0]['name'])->toBe('Acme Care')
        ->and($rows[0]['tier'])->toBe('premium')
        ->and($rows[0]['filename'])->toBe('acme-prv-1000.pdf')
        ->and($rows[0]['related_cc_refs'])->toBe(['1111111', '2222222', '3333333'])
        ->and($rows[1]['related_cc_refs'])->toBe([]);
});

it('matches headers case- and punctuation-insensitively', function () {
    $csv = tempnam(sys_get_temp_dir(), 'far').'.csv';
    file_put_contents($csv, "PROVIDER_REF,provider name,TIER,file,related cc refs\nPRV-3000,Gamma,standard,g.pdf,4444444\n");

    $rows = FarIndexFile::read($csv);

    @unlink($csv);

    expect($rows[0]['provider_ref'])->toBe('PRV-3000')
        ->and($rows[0]['filename'])->toBe('g.pdf')
        ->and($rows[0]['related_cc_refs'])->toBe(['4444444']);
});
