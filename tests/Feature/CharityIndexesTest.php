<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('indexes the charity search columns', function () {
    $indexes = collect(Schema::getIndexes('charities'))
        ->pluck('columns')
        ->flatten()
        ->all();

    expect($indexes)->toContain('latest_q_score')
        ->and($indexes)->toContain('latest_stability');
});
