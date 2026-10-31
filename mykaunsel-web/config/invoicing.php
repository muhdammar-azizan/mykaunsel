<?php

/*
 * Invoices (docs/PAYMENTS_PLAN.md): the yearly subscription of a university, corporate or clinic is invoiced like a real invoice, with a tax line.
 * These are only the STARTING values. A Platform Admin changes them under Pricing & Promotions > Invoice settings; they are stored in
 * `platform_settings` (key invoice.settings), and each invoice keeps the values it was made with.
 */
return [

    // "INV-2026-0001": the prefix, the year the invoice was issued, and a number that restarts every year.
    'prefix' => 'INV',

    'currency' => 'MYR',

    'defaults' => [
        // Tax: the name shown on the invoice and its rate in basis points (100 = 1%; 800 = 8%). 0 while this is a final year project.
        'tax_name' => 'SST',
        'tax_rate_bp' => 0,

        // Days from the day an invoice is issued to the day it falls due.
        'due_days' => 14,

        // Who is selling, and where to pay by bank transfer (an organization can pay a large invoice that way).
        'seller_name' => 'MyKaunsel',
        'seller_address' => '',
        'seller_ssm' => '',
        'seller_tax_no' => '',
        'bank_name' => '',
        'bank_account_name' => '',
        'bank_account_no' => '',
    ],

    'limits' => [
        'due_days' => [1, 90],
    ],

];
