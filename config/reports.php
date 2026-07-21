<?php

// Allowed FAR tiers. Placeholder labels pending client confirmation
// (spec 2026-07-17 §2: "validated on import against an admin-configurable
// allowed list" — admin UI arrives with M4; config is the M1.5 source).
return [
    'far_tiers' => ['standard', 'enhanced', 'premium'],

    // When false (local dev without an S3 bucket), index imports skip ONLY
    // the S3 file-existence check; all other row validation still runs.
    // See docs/superpowers/specs/2026-07-21-import-file-check-bypass-design.md.
    'validate_import_files' => env('IMPORT_VALIDATE_FILES', true),
];
