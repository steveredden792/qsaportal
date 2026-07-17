<?php

use App\Enums\AssetType;
use App\Enums\ReportType;

it('exposes the two report types', function () {
    expect(ReportType::cases())->toHaveCount(2);
    expect(ReportType::PIR->value)->toBe('pir');
    expect(ReportType::FAR->value)->toBe('far');
});

it('exposes the asset types', function () {
    expect(AssetType::ReportPdf->value)->toBe('report_pdf');
    expect(AssetType::Dataset->value)->toBe('dataset');
    expect(AssetType::Teaser->value)->toBe('teaser');
});
