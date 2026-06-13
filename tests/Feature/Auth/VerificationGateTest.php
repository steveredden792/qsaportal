<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unverified users away from the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirect('/verify-email');
});

it('allows verified users to view the dashboard', function () {
    $user = User::factory()->create(); // verified by default

    $this->actingAs($user)->get('/dashboard')->assertOk();
});
