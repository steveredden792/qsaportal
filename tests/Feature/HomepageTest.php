<?php

it('links to registration for guests from the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('register'));
});

it('links to the PIR database for authenticated users', function () {
    $this->actingAs(\App\Models\User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee(route('catalogue.pir'), false);
});
