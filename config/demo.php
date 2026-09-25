<?php

// Demo-only switches. Keep every default false so production behaviour is
// unchanged unless the environment explicitly opts in.
return [
    // When true, a completed checkout is fulfilled immediately in-app and the
    // buyer is sent straight to the success page, bypassing the payment gateway
    // and its webhook. For local demos only — never enable in production.
    'instant_fulfil' => (bool) env('DEMO_INSTANT_FULFIL', false),

    // Disk + key used as the downloadable report PDF for seeded demo data.
    // Defaults to a bundled local sample so the download works with no S3 setup.
    // Point at a real S3 object instead with DEMO_SAMPLE_REPORT_DISK=s3 and
    // DEMO_SAMPLE_REPORT_PDF=pir/<folder>/<file>.pdf.
    'sample_report_disk' => env('DEMO_SAMPLE_REPORT_DISK', 'local'),
    'sample_report_pdf' => env('DEMO_SAMPLE_REPORT_PDF', 'demo/sample-report.pdf'),
];
