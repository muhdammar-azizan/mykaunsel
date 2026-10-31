<?php

/*
 * The public pages that explain MyKaunsel's rules: Terms of Use, Privacy Policy and Help (/terms, /privacy, /help).
 *
 * The first versions are DRAFTS written from what the system really does. While `draft` is on, every page says so at the top, and the places
 * that still need a fact only the owner knows are marked "To be confirmed" (yellow). Turn it off (LEGAL_DRAFT=false) only after the owner and a
 * lawyer have reviewed the text. See docs/LEGAL_PAGES.md.
 */
return [
    'draft' => (bool) env('LEGAL_DRAFT', true),

    // The date shown as "last updated". Change it whenever the text changes.
    'updated' => '31 October 2026',
];
