<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContextSelectionController;
use App\Http\Controllers\Counselor\CounselorAppointmentController;
use App\Http\Controllers\Counselor\CounselorDashboardController;
use App\Http\Controllers\Counselor\CounselorPlannerController;
use App\Http\Controllers\Counselor\CounselorProfileController;
use App\Http\Controllers\CounselorSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\Organization\OrgCounselorController;
use App\Http\Controllers\Organization\OrgDashboardController;
use App\Http\Controllers\Organization\OrgMemberController;
use App\Http\Controllers\Organization\OrgSettingController;
use App\Http\Controllers\Platform\PlatformComplaintController;
use App\Http\Controllers\Platform\PlatformCounselorController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformOrganizationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/select-context', [ContextSelectionController::class, 'index'])->name('context.select');
    Route::post('/select-context', [ContextSelectionController::class, 'store'])->name('context.store');

    Route::get('/membership-blocked', function () {
        return view('auth.membership-blocked');
    })->name('membership.blocked');

    Route::get('/organization-pending', function () {
        return view('auth.organization-pending');
    })->name('organization.pending');

    Route::get('/organization-suspended', function () {
        return view('auth.organization-suspended');
    })->name('organization.suspended');

    // Preview-only routes: not yet wired into the real verification.verify
    // redirect flow (see docs/design-conversion notes). New route names,
    // not part of the original spec — flagged for review.
    Route::get('/verify-email/success', function () {
        return view('auth.verify-email-success');
    })->name('verification.success');

    Route::get('/verify-email/expired', function () {
        return view('auth.verify-email-expired');
    })->name('verification.expired');
});

Route::middleware(['auth', 'verified', 'org.context', 'membership.active'])->group(function () {
    Route::prefix('org')->name('org.')->middleware('role:org_admin')->group(function () {
        Route::get('/dashboard', [OrgDashboardController::class, 'index'])->name('dashboard');
        Route::get('/counselors', [OrgCounselorController::class, 'index'])->name('counselors');
        Route::get('/members', [OrgMemberController::class, 'index'])->name('members');
        Route::get('/settings', [OrgSettingController::class, 'index'])->name('settings');
    });

    Route::prefix('counselor')->name('counselor.')->middleware('role:counselor')->group(function () {
        Route::get('/dashboard', [CounselorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/planner', [CounselorPlannerController::class, 'index'])->name('planner');
        Route::get('/appointments', [CounselorAppointmentController::class, 'index'])->name('appointments');
        Route::get('/profile', [CounselorProfileController::class, 'index'])->name('profile');
    });

    Route::prefix('platform')->name('platform.')->middleware('role:platform_admin')->group(function () {
        Route::get('/dashboard', [PlatformDashboardController::class, 'index'])->name('dashboard');
        Route::get('/organizations', [PlatformOrganizationController::class, 'index'])->name('organizations');
        Route::get('/counselors', [PlatformCounselorController::class, 'index'])->name('counselors');
        Route::get('/complaints', [PlatformComplaintController::class, 'index'])->name('complaints');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/counselors', [CounselorSearchController::class, 'index'])->name('counselors.index');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
