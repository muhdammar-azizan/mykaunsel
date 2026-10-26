<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CounselorDirectoryController;
use App\Http\Controllers\Api\V1\StatusController;
use Illuminate\Support\Facades\Route;

/*
 * The mobile app's API. Everything here answers in JSON, obeys maintenance mode (only `status` still answers) and uses
 * Sanctum bearer tokens. See docs/API.md for the rules every new endpoint must follow.
 * Rate limits are defined in AppServiceProvider::apiRateLimits (mobile networks share IPs, so they follow the email /
 * challenge / person, not just the IP).
 */
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public.
    Route::get('/status', StatusController::class)->middleware('throttle:api-public')->name('status');

    // Sign-up can be closed by a Platform Admin (Registration Control).
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware(['registration.open:individual', 'throttle:api-register'])->name('auth.register');

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('auth.login');
    Route::post('/auth/two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:api-two-factor')->name('auth.two-factor');
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:api-email')->name('auth.email.resend');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:api-email')->name('auth.forgot-password');

    // Signed in with a token and with a confirmed email address.
    Route::middleware(['auth:sanctum', 'verified', 'throttle:api-user'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
    });

    // The signed-in person's own account. Anything that asks for the current password is limited to a few tries a minute.
    Route::middleware(['auth:sanctum', 'verified', 'throttle:api-user'])->prefix('account')->name('account.')->group(function () {
        Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile');
        Route::get('/devices', [AccountController::class, 'devices'])->name('devices');

        Route::middleware('throttle:api-sensitive')->group(function () {
            Route::put('/password', [AccountController::class, 'updatePassword'])->name('password');
            Route::post('/devices/{device}/revoke', [AccountController::class, 'revokeDevice'])->whereNumber('device')->name('devices.revoke');
        });
    });

    // Counselor search (docs/PUBLIC_COUNSELOR_DATA.md). Signed in with a confirmed email only, with its own limit.
    Route::middleware(['auth:sanctum', 'verified', 'throttle:api-directory'])->prefix('counselors')->name('counselors.')->group(function () {
        Route::get('/', [CounselorDirectoryController::class, 'index'])->name('index');
        // Before {id}, so "filters" is never read as a counselor id.
        Route::get('/filters', [CounselorDirectoryController::class, 'filters'])->name('filters');
        Route::get('/{publicId}', [CounselorDirectoryController::class, 'show'])->name('show');
    });
});
