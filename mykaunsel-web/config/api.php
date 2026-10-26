<?php

/*
 * Settings for the mobile app's API (see docs/API.md).
 */
return [

    // The oldest app version still allowed to use the API. Every API call from an older app (it sends X-App-Version)
    // gets 426 "app_update_required" and the app shows an update screen. Raise it only when an API change would break
    // the older apps. 0.0.0 = nobody is forced to update.
    'min_app_version' => env('API_MIN_APP_VERSION', '0.0.0'),

    'update_message' => 'This version of MyKaunsel is out of date. Please update the app to continue.',

    // Where the app sends people to update (fill in once the app is published).
    'store_urls' => [
        'android' => env('APP_STORE_URL_ANDROID'),
        'ios' => env('APP_STORE_URL_IOS'),
    ],
];
