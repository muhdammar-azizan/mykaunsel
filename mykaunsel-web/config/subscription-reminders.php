<?php

/*
 * Warnings before an organization's subscription ends (docs/PAYMENTS_PLAN.md, "Subscription end date", decided 2 November 2026). Each number is a decision
 * that can be changed here. A warning is sent once for each step and each end date: after a renewal the end date is a new one, so the steps start again.
 */
return [
    // Days before the last day of the plan that the organization's admins are told (e-mail and the bell). One more e-mail when it has ended.
    'admin_days' => [60, 30, 14, 7],

    // Days before the last day that the organization's counselors are told (e-mail only): the times they opened after it will be hidden.
    'counselor_days' => [30, 14],
];
