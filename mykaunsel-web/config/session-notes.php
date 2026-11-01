<?php

/*
 * Counselor session notes (docs/SESSION_NOTES.md). The most sensitive text in the system: encrypted, read only by the counselor who wrote it.
 * Each number is a decision that can be changed here.
 */
return [
    // Longest note, in characters. Text only, no files.
    'max_length' => 5000,

    // A note is erased this many years after the session it is about. The period is the owner's and a lawyer's to confirm (7 is a placeholder).
    'retention_years' => 7,

    'per_page' => 20,
];
