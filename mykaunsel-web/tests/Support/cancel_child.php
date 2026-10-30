<?php

/**
 * Child process of BookingConcurrencyTest: waits until a shared start time, then cancels ONE booking as one person through the whole
 * application (routes, middleware, controller) and prints "<http status> <code or ->". Not a test itself.
 *
 * usage: php cancel_child.php <user id> <booking public id> <start microtime>
 */

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $userId, $booking, $start] = $argv;

Sanctum::actingAs(User::findOrFail((int) $userId));

$request = Request::create('/api/v1/bookings/'.$booking.'/cancel', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1']);

while (microtime(true) < (float) $start) {
    usleep(200);
}

$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);

echo $response->getStatusCode().' '.(json_decode($response->getContent(), true)['code'] ?? '-')."\n";
