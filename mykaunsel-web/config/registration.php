<?php

/*
 * Who can sign up on the WEB (see docs/REGISTRATION_CONTROL.md). The Registration Control page (Platform Admin) can close
 * a kind of sign-up at any time; the settings here decide whether a door exists at all.
 */
return [

    // Individuals (clients) sign up in the MOBILE APP only. The web sign-up form and its Blade views are kept for a future
    // web version, but while this is false the web routes GET/POST /register answer 404 (they are not redirected anywhere)
    // and no public page links to them. Only turn it on locally / in tests (so developers can create test users), or when
    // a web version for individuals really launches. When it is on, the "Individuals" switch in Registration Control still
    // closes it. The mobile app's own register route (POST /api/v1/auth/register) is not affected by this setting.
    'web_individual_enabled' => (bool) env('REGISTRATION_WEB_INDIVIDUAL_ENABLED', false),

    // Where "Get the mobile app" leads. For now the placeholder download section on the landing page; point it at the
    // store listing (or a smart link) when the app is published.
    'mobile_app_url' => env('MOBILE_APP_URL', '/#get-the-app'),
];
