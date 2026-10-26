<?php

/*
 * LKM Directory: CSV import and export (Platform Admin → LKM Directory).
 */
return [

    'import' => [
        // A file waiting between "preview" and "import" lives on this PRIVATE disk (see config/filesystems.php), under a random
        // name, and is deleted when it is imported or cancelled, or after `keep_hours` if the admin walks away.
        'disk' => env('LKM_IMPORT_DISK', 'lkm_imports'),
        'keep_hours' => 24,

        'max_bytes' => 2 * 1024 * 1024,
        'max_rows' => 5000,

        // Errors listed on the page; the downloadable report has all of them.
        'errors_shown' => 100,
        // Changed records listed in the preview.
        'updates_shown' => 25,
    ],

    // A counselor's request to re-check their LKM record (Platform Admin → LKM Directory → Review Requests).
    'review' => [
        // The evidence (a photo or PDF of the renewed certificate): a PRIVATE disk (config/filesystems.php), random names,
        // opened only through short-lived signed links for one Platform Admin, every opening audited.
        'disk' => env('LKM_REVIEW_EVIDENCE_DISK', 'lkm_review_evidence'),
        'link_minutes' => 2,
        'max_kb' => 5120,
        'extensions' => ['pdf', 'jpg', 'jpeg', 'png'],

        // The file is deleted this many days after the decision (`lkm:purge-review-evidence`, daily; --dry-run to look).
        'evidence_retention_days' => (int) env('LKM_REVIEW_EVIDENCE_RETENTION_DAYS', 30),
        'purge_chunk' => 200,

        'per_page' => 10,
    ],

    // The most records one export can hold.
    'export_max_rows' => 10000,
    'export_chunk' => 500,
];
