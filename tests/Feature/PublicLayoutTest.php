<?php

it('renders the public layout component with a title and slot', function () {
    $this->blade('<x-public title="Probe">PROBE_CONTENT</x-public>')
        ->assertSee('PROBE_CONTENT')
        ->assertSee('QS Analysis');
});
