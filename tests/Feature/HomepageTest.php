<?php

it('links to the FAR catalogue from the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('catalogue.far'));
});
