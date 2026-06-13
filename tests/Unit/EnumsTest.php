<?php

use App\Enums\AssetType;
use App\Enums\ReportType;

it('exposes the three report types', function () {
    expect(ReportType::cases())->toHaveCount(3);
    expect(ReportType::FAR->value)->toBe('far');
    expect(ReportType::PPR->value)->toBe('ppr');
    expect(ReportType::PMR->value)->toBe('pmr');
});

it('exposes the asset types', function () {
    expect(AssetType::ReportPdf->value)->toBe('report_pdf');
    expect(AssetType::Dataset->value)->toBe('dataset');
    expect(AssetType::Teaser->value)->toBe('teaser');
});
