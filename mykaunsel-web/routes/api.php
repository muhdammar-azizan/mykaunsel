<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\CounselorDirectoryController;
use App\Http\Controllers\Api\V1\MyBookingController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\TimeRequestController;
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
    Route::post('/auth/signup-link', [AuthController::class, 'signupLink'])->middleware('throttle:api-email')->name('auth.signup-link');
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
        Route::put('/ai-consent', [AccountController::class, 'updateAiConsent'])->name('ai-consent');
        Route::get('/notification-preferences', [AccountController::class, 'notificationPreferences'])->name('notification-preferences');
        Route::put('/notification-preferences', [AccountController::class, 'updateNotificationPreference'])->name('notification-preferences.update');

        // The organization the person belongs to (or waits to join), joining with a reference code, and leaving.
        Route::get('/organization', [OrganizationController::class, 'show'])->name('organization');
        Route::middleware('throttle:api-organization')->group(function () {
            Route::post('/organization/join', [OrganizationController::class, 'join'])->name('organization.join');
            Route::delete('/organization', [OrganizationController::class, 'leave'])->name('organization.leave');
        });

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

    // The chatbot (docs/CHAT.md). Sending, asking again and ending share one limit; reading the history is an ordinary read. The helplines need no
    // sign-in at all: help is never behind a setting.
    Route::get('/chat/helplines', [ChatController::class, 'helplines'])->middleware('throttle:60,1')->name('chat.helplines');
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-chat'])->prefix('chat')->name('chat.')->group(function () {
        Route::post('/messages', [ChatController::class, 'send'])->name('send');
        Route::post('/sessions/{publicId}/reply', [ChatController::class, 'reply'])->where('publicId', '[0-9A-Za-z]{26}')->name('reply');
        Route::post('/sessions/{publicId}/end', [ChatController::class, 'end'])->where('publicId', '[0-9A-Za-z]{26}')->name('end');
    });
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-user'])->prefix('chat')->name('chat.')->group(function () {
        Route::get('/sessions', [ChatController::class, 'index'])->name('index');
        Route::get('/sessions/{publicId}', [ChatController::class, 'show'])->where('publicId', '[0-9A-Za-z]{26}')->name('show');
    });

    // The wellness Feed (docs/FEED.md). Writing (posts, comments, edits, deletes, reports, mutes) shares one limit, reacting has its own, reading is an
    // ordinary read. Ids are 26 character public ids.
    Route::middleware(['auth:sanctum', 'api.verified'])->prefix('feed')->name('feed.')->group(function () {
        $id = '[0-9A-Za-z]{26}';

        Route::middleware('throttle:api-user')->group(function () use ($id) {
            Route::get('/', [FeedController::class, 'index'])->name('index');
            Route::get('/posts/{id}', [FeedController::class, 'show'])->where('id', $id)->name('show');
            Route::get('/posts/{id}/comments', [FeedController::class, 'comments'])->where('id', $id)->name('comments');
            Route::get('/mutes', [FeedController::class, 'mutes'])->name('mutes');
        });

        Route::middleware('throttle:api-feed-write')->group(function () use ($id) {
            Route::post('/posts', [FeedController::class, 'store'])->name('store');
            Route::patch('/posts/{id}', [FeedController::class, 'update'])->where('id', $id)->name('update');
            Route::delete('/posts/{id}', [FeedController::class, 'destroy'])->where('id', $id)->name('destroy');
            Route::post('/posts/{id}/comments', [FeedController::class, 'comment'])->where('id', $id)->name('comment');
            Route::delete('/comments/{id}', [FeedController::class, 'destroyComment'])->where('id', $id)->name('comment.destroy');
            Route::post('/posts/{id}/report', [FeedController::class, 'report'])->where('id', $id)->name('report');
            Route::post('/comments/{id}/report', [FeedController::class, 'report'])->where('id', $id)->name('comment.report');
            Route::post('/posts/{id}/mute-author', [FeedController::class, 'muteAuthor'])->where('id', $id)->name('mute');
            Route::delete('/mutes/{id}', [FeedController::class, 'unmute'])->where('id', $id)->name('unmute');
        });

        Route::middleware('throttle:api-feed-react')->group(function () use ($id) {
            Route::put('/posts/{id}/reaction', [FeedController::class, 'react'])->where('id', $id)->name('react');
            Route::delete('/posts/{id}/reaction', [FeedController::class, 'react'])->where('id', $id)->name('unreact');
        });
    });

    // Mode B: asking a counselor for a time (docs/AVAILABILITY.md, "Time requests"). Asking and withdrawing share one limit.
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-availability'])->get('/counselors/{publicId}/working-hours', [TimeRequestController::class, 'hours'])->where('publicId', '[0-9A-Za-z]{26}')->name('counselors.working-hours');
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-booking-request'])->post('/counselors/{publicId}/requests', [TimeRequestController::class, 'store'])->where('publicId', '[0-9A-Za-z]{26}')->name('requests.store');
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-user'])->prefix('requests')->name('requests.')->group(function () {
        Route::get('/', [TimeRequestController::class, 'index'])->name('index');
        Route::get('/{publicId}', [TimeRequestController::class, 'show'])->where('publicId', '[0-9A-Za-z]{26}')->name('show');
    });
    Route::middleware(['auth:sanctum', 'api.verified', 'throttle:api-booking-request'])->post('/requests/{publicId}/withdraw', [TimeRequestController::class, 'withdraw'])->where('publicId', '[0-9A-Za-z]{26}')->name('requests.withdraw');

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
