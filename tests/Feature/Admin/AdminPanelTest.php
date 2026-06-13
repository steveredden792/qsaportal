<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves the admin login page', function () {
    $this->get('/admin/login')->assertOk();
});
