<?php

it('links to the PIR database from the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('catalogue.pir'));
});
