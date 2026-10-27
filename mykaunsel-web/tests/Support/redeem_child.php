<?php

/**
 * Child process of PricingConcurrencyTest: waits until a shared start time, then gives one organization a plan with a promo code and
 * prints OK or REFUSED. Not a test itself.   usage: php redeem_child.php <org id> <plan id> <admin id> <code> <start microtime>
 */

use App\Exceptions\PricingChangeBlocked;
use App\Models\Organization;
use App\Models\User;
use App\Services\SubscriptionAssignmentService;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $orgId, $planId, $adminId, $code, $start] = $argv;

while (microtime(true) < (float) $start) {
    usleep(200);
}

try {
    app(SubscriptionAssignmentService::class)->assign(User::findOrFail($adminId), Organization::findOrFail($orgId), (int) $planId, $code);
    echo "OK\n";
} catch (PricingChangeBlocked $e) {
    echo "REFUSED\n";
}
