<?php

/*
 * Pricing & Promotions (Platform Admin → Pricing & Promotions). There is NO payment: nothing is charged. These are records
 * of plans, prices and promo codes, and which organization is on which plan. Prices shown exclude tax (SST).
 */
return [

    // Percentage of the session price kept by the platform for clinics and independent counselors (record only).
    // This is the example value used until an admin saves one; it is stored in `platform_settings` (key pricing.commission_percent).
    'commission_percent_default' => 10,

    // Limits checked on the server for every plan.
    'plan' => [
        'max_members' => 1_000_000,
        'max_price_cents' => 100_000_000, // RM 1,000,000.00 a year
    ],

];
