<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a verified local admin user', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'admin@qsanalysis.local')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('QSA Admin')
        ->and($admin->hasVerifiedEmail())->toBeTrue();
});

it('is idempotent and keeps the admin verified', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', 'admin@qsanalysis.local')->count())->toBe(1)
        ->and(User::where('email', 'admin@qsanalysis.local')->first()->hasVerifiedEmail())->toBeTrue();
});
