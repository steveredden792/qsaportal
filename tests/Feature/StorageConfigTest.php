<?php

it('has an s3 disk configured', function () {
    expect(config('filesystems.disks.s3.driver'))->toBe('s3');
    expect(config('filesystems.disks.s3.bucket'))->not->toBeNull();
});
