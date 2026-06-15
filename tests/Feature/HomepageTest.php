<?php

it('links to all three catalogues from the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('catalogue.far'))
        ->assertSee(route('catalogue.ppr'))
        ->assertSee(route('catalogue.pmr'));
});
