<?php

it('redirects guests to register when search access requires registration', function () {
    config()->set('app.require_registration_for_search', true);
    config()->set('app.allow_registration', true);

    $response = $this->get('/catalogue/pir');

    $response->assertRedirect('/register');
});

it('redirects guests to login when registration is disabled but search access is required', function () {
    config()->set('app.require_registration_for_search', true);
    config()->set('app.allow_registration', false);

    $response = $this->get('/catalogue/pir');

    $response->assertRedirect('/login');
});

it('allows guests to browse the catalogue when registration is not required', function () {
    config()->set('app.require_registration_for_search', false);

    $response = $this->get('/catalogue/pir');

    $response->assertOk();
});
