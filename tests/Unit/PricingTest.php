<?php

use App\Support\Money;
use App\Support\Pricing;
use Tests\TestCase;

uses(TestCase::class);

it('formats pence as pounds', function () {
    expect(Money::format(2500))->toBe('£25.00')
        ->and(Money::format(0))->toBe('£0.00')
        ->and(Money::format(187500))->toBe('£1,875.00');
});

it('returns configured prices in pence', function () {
    expect(Pricing::for('pir', 'single'))->toBe(2500);
});

it('returns null for an unknown price', function () {
    expect(Pricing::for('far', 'single'))->toBeNull()
        ->and(Pricing::for('unknown', 'standard'))->toBeNull();
});
