<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CounselorDirectoryController;
use App\Http\Controllers\Api\V1\MyBookingController;
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
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-user'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
    });

    // The signed-in person's own account. Anything that asks for the current password is limited to a few tries a minute.
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-user'])->prefix('account')->name('account.')->group(function () {
        Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile');
        Route::get('/devices', [AccountController::class, 'devices'])->name('devices');

        Route::middleware('throttle:api-sensitive')->group(function () {
            Route::put('/password', [AccountController::class, 'updatePassword'])->name('password');
            Route::post('/devices/{device}/revoke', [AccountController::class, 'revokeDevice'])->whereNumber('device')->name('devices.revoke');
        });
    });

    // Booking a time (docs/AVAILABILITY.md, "The confirm step"). The preview claims nothing; the POST is the only place a Mode A booking is
    // made. Each has its own limit.
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-booking-preview'])->get('/bookings/preview', [BookingController::class, 'preview'])->name('bookings.preview');
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-booking-create'])->post('/bookings', [BookingController::class, 'store'])->name('bookings.store');

    // My bookings: the person's own, read only (docs/AVAILABILITY.md, "My bookings"). The id is a 26 character public id, which keeps it apart
    // from /bookings/preview.
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-user'])->prefix('bookings')->name('bookings.')->group(function () {
        Route::get('/', [MyBookingController::class, 'index'])->name('index');
        Route::get('/{publicId}', [MyBookingController::class, 'show'])->where('publicId', '[0-9A-Za-z]{26}')->name('show');
    });

    // Cancelling one's own booking. Its own limit; safe to repeat.
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-booking-cancel'])
        ->post('/bookings/{publicId}/cancel', [MyBookingController::class, 'cancel'])->where('publicId', '[0-9A-Za-z]{26}')->name('bookings.cancel');

    // Moving one's own booking to another time of the same counselor. Its own limit; safe to repeat.
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-booking-reschedule'])
        ->post('/bookings/{publicId}/reschedule', [MyBookingController::class, 'reschedule'])->where('publicId', '[0-9A-Za-z]{26}')->name('bookings.reschedule');

    // Counselor search (docs/PUBLIC_COUNSELOR_DATA.md). Signed in with a confirmed email only, with its own limit.
    // Which times of one counselor can be picked (read only; the booking itself is a later step). Its own, roomier limit.
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-availability'])->get('/counselors/{publicId}/availability', AvailabilityController::class)->name('counselors.availability');

    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-directory'])->prefix('counselors')->name('counselors.')->group(function () {
        Route::get('/', [CounselorDirectoryController::class, 'index'])->name('index');
        // Before {id}, so "filters" is never read as a counselor id.
        Route::get('/filters', [CounselorDirectoryController::class, 'filters'])->name('filters');
        Route::get('/{publicId}', [CounselorDirectoryController::class, 'show'])->name('show');
    });
});
