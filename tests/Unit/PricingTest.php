<?php

use App\Support\Money;
use App\Support\Pricing;

it('formats pence as pounds', function () {
    expect(Money::format(2500))->toBe('£25.00')
        ->and(Money::format(0))->toBe('£0.00')
        ->and(Money::format(187500))->toBe('£1,875.00');
});

it('returns configured prices in pence', function () {
    expect(Pricing::for('far', 'single'))->toBe(2500)
        ->and(Pricing::for('ppr', 'standard'))->toBe(5000)
        ->and(Pricing::for('ppr', 'enhanced'))->toBe(7500)
        ->and(Pricing::for('ppr', 'premium'))->toBe(10000)
        ->and(Pricing::for('pmr', 'standard'))->toBe(5000)
        ->and(Pricing::for('pmr', 'premium'))->toBe(10000);
});

it('returns null for an unknown price', function () {
    expect(Pricing::for('pmr', 'enhanced'))->toBeNull();
});
