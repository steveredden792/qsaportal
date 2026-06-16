<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Never seed a default-password admin into production.
        if (app()->environment('production')) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@qsanalysis.local'],
            ['name' => 'QSA Admin', 'password' => 'password'],
        );

        // email_verified_at is not mass-assignable on the User model, so set it explicitly.
        if (! $admin->hasVerifiedEmail()) {
            $admin->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
