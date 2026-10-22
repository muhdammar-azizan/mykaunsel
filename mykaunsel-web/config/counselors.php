<?php

/*
 * Counselor verification settings (Platform Admin → Counselors).
 */
return [

    // Where KB / PA / ID documents live. Must be a PRIVATE disk (see config/filesystems.php) — switch to a private
    // S3/R2 disk by pointing this at it. Nothing under it is ever reachable by a public URL.
    'documents_disk' => env('COUNSELOR_DOCUMENTS_DISK', 'counselor_documents'),

];
