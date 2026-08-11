<?php

it('allows registration routes when enabled', function () {
    config()->set('app.allow_registration', true);

    $response = $this->get('/register');

    $response->assertStatus(200);
});

it('hides registration routes when disabled', function () {
    config()->set('app.allow_registration', false);

    $response = $this->get('/register');

    $response->assertStatus(404);
});
