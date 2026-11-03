<?php

/*
 * The public website (landing page and, later, its sub pages). Structure: docs/LANDING_PAGE.md.
 * All words are in lang/{ms,en}/landing.php; this file holds the switches and the links.
 */
return [

    // Invented content (social proof logos, quotes and figures, the Problem section) is only a placeholder. This switch decides whether it is shown.
    // Off in production by default: those blocks then disappear and the page closes up with no gap. Set LANDING_SHOW_PLACEHOLDER=true to force them on.
    'show_placeholder_content' => (bool) env('LANDING_SHOW_PLACEHOLDER', env('APP_ENV', 'production') !== 'production'),

    // The blocks above that are placeholders. A block not in this list is always shown.
    'placeholder_blocks' => ['proof_logos', 'proof_quotes', 'proof_figures', 'problem'],

    // An invented address on a reserved example domain (it can never reach a real person). Replace with the real one before launch.
    'contact_email' => env('LANDING_CONTACT_EMAIL', 'hello@mykaunsel.example'),

    'locales' => ['ms', 'en'],
    'default_locale' => 'ms',

    // Pages that are not built yet and are meant to stay a "Coming soon" page for a while: they show a small "Soon" tag next to their name.
    // (Other pages of the planned site that are not built yet also open the Coming soon page, but without a tag, until their turn comes.)
    'soon_tagged' => ['wellness-feed', 'mental-health-articles', 'glossary', 'status', 'sitemap'],

    // Pages that really exist: slug => route name. Every other slug opens the Coming soon page (route landing.soon).
    'built' => [
        'terms' => 'terms',
        'privacy' => 'privacy',
        'faq-help' => 'help',
        'safety-crisis' => 'help',
        'join-waitlist' => 'register.type',
    ],

    // Urgent help. Numbers must be checked again before launch (docs/LANDING_PAGE.md). A line without a number links to the organization's own site.
    'urgent_help' => [
        ['name' => 'HEAL', 'number' => '15555', 'url' => null],
        ['name' => 'Talian Kasih', 'number' => '15999', 'url' => null],
        ['name' => 'Befrienders', 'number' => null, 'url' => 'https://www.befrienders.org.my'],
    ],
];
