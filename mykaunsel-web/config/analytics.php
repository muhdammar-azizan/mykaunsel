<?php

/*
 * Platform Admin → Analytics. Read-only trends computed from live tables (App\Services\AnalyticsService). Aggregates only: no
 * name, email, KB number or id of a person ever appears on the page or in the export.
 */
return [

    // A category with a count from 1 up to (this - 1) is not shown as a number: "<5" in the organization-type view, grouped into
    // "Other (fewer than 5)" in the specialization views. This stops casual identification; it is not formal anonymity.
    'small_number_threshold' => (int) env('ANALYTICS_SMALL_NUMBER_THRESHOLD', 5),

    // How long a computed report is kept (the Refresh button clears it).
    'cache_minutes' => (int) env('ANALYTICS_CACHE_MINUTES', 5),

    // The range: the longest allowed, the default and the quick choices (days, ending today).
    'max_days' => 366,
    'default_days' => 30,
    'presets' => [7, 30, 90],

    // The chart bucket follows the length of the range: daily up to this many days, weekly up to the next, monthly beyond.
    'daily_max_days' => 31,
    'weekly_max_days' => 180,

];
