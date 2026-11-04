<?php

/**
 * Child process of CounselorSignupConcurrencyTest: waits until a shared start time, then sends ONE counselor sign up through the real web
 * stack (routes, middleware, controller) and prints what happened: OK (signed up), REFUSED:<field> (a clear message) or ERROR:<status> (an error page).
 * Not a test itself.   usage: php signup_child.php <kb> <pa> <name> <email> <ip> <start microtime>
 */

use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

[, $kb, $pa, $name, $email, $ip, $start] = $argv;

while (microtime(true) < (float) $start) {
    usleep(200);
}

$request = Request::create('/counselors/signup', 'POST', [
    'name' => $name, 'email' => $email, 'kb_number' => $kb, 'pa_number' => $pa,
    'password' => 'Secret#Pass99', 'password_confirmation' => 'Secret#Pass99', 'confirm_accurate' => 1, 'confirm_terms' => 1,
], [], [], ['REMOTE_ADDR' => $ip]);

try {
    $response = $kernel->handle($request);
} catch (Throwable $e) {
    echo 'ERROR:exception '.get_class($e)."\n";
    exit;
}

$location = (string) $response->headers->get('Location');

if ($response->isRedirection() && str_contains($location, '/counselors/signup/verified')) {
    echo "OK\n";
} elseif ($response->isRedirection()) {
    $errors = $request->hasSession() ? $request->session()->get('errors') : null;
    echo 'REFUSED:'.implode(',', $errors ? array_keys($errors->getBag('default')->toArray()) : ['unknown'])."\n";
} else {
    echo 'ERROR:'.$response->getStatusCode()."\n";
}
