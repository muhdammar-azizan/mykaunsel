<?php

use App\Http\Controllers\Auth\AccountTypeController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContextSelectionController;
use App\Http\Controllers\Counselor\CounselorApprovalStatusController;
use App\Http\Controllers\Counselor\CounselorAppointmentController;
use App\Http\Controllers\Counselor\CounselorDashboardController;
use App\Http\Controllers\Counselor\CounselorDocumentController;
use App\Http\Controllers\Counselor\CounselorPlannerController;
use App\Http\Controllers\Counselor\CounselorProfileController;
use App\Http\Controllers\Counselor\CounselorSignupController;
use App\Http\Controllers\CounselorSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\Organization\OrgApprovalStatusController;
use App\Http\Controllers\Organization\OrgCounselorController;
use App\Http\Controllers\Organization\OrgDashboardController;
use App\Http\Controllers\Organization\OrgDomainVerificationController;
use App\Http\Controllers\Organization\OrgMemberController;
use App\Http\Controllers\Organization\OrgSettingController;
use App\Http\Controllers\Organization\OrgSignupController;
use App\Http\Controllers\Platform\AccountAvatarController;
use App\Http\Controllers\Platform\AccountController;
use App\Http\Controllers\Platform\AccountEmailController;
use App\Http\Controllers\Platform\AccountNotificationController;
use App\Http\Controllers\Platform\AccountSecurityController;
use App\Http\Controllers\Platform\AccountTwoFactorController;
use App\Http\Controllers\Platform\PlatformAdminController;
use App\Http\Controllers\Platform\PlatformAuditLogController;
use App\Http\Controllers\Platform\PlatformComplaintActionController;
use App\Http\Controllers\Platform\PlatformComplaintController;
use App\Http\Controllers\Platform\PlatformCounselorController;
use App\Http\Controllers\Platform\PlatformCounselorDocumentController;
use App\Http\Controllers\Platform\PlatformCounselorReviewController;
use App\Http\Controllers\Platform\PlatformCounselorStatusController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformLkmDirectoryController;
use App\Http\Controllers\Platform\PlatformLkmImportController;
use App\Http\Controllers\Platform\PlatformLkmReviewController;
use App\Http\Controllers\Platform\PlatformOrganizationController;
use App\Http\Controllers\Platform\PlatformOrganizationDecisionController;
use App\Http\Controllers\Platform\PlatformOrganizationDnsController;
use App\Http\Controllers\Platform\PlatformOrganizationReviewController;
use App\Http\Controllers\Platform\PlatformPricingController;
use App\Http\Controllers\Platform\PlatformRegistrationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// A placeholder until the real Privacy Policy is written (the waitlist consent line links here).
Route::view('/privacy', 'privacy')->name('privacy');

// Waitlist for closed sign-ups (Organization and Counselor): the form on the "sign-up closed" page, and the opt-out link in the
// email. Opting out works even during maintenance and is never blocked by a closed sign-up. See docs/WAITLIST.md.
Route::post('/waitlist/{type}', [\App\Http\Controllers\WaitlistController::class, 'store'])
    ->whereIn('type', ['organization', 'counselor'])->name('waitlist.store');
Route::get('/waitlist/unsubscribe/{type}/{hash}/{signature}', [\App\Http\Controllers\WaitlistController::class, 'confirm'])
    ->whereIn('type', ['organization', 'counselor'])->where(['hash' => '[a-f0-9]{64}', 'signature' => '[a-f0-9]{64}'])->name('waitlist.unsubscribe');
Route::post('/waitlist/unsubscribe/{type}/{hash}/{signature}', [\App\Http\Controllers\WaitlistController::class, 'optOut'])
    ->whereIn('type', ['organization', 'counselor'])->where(['hash' => '[a-f0-9]{64}', 'signature' => '[a-f0-9]{64}'])->middleware('throttle:30,1')->name('waitlist.unsubscribe.store');

// Opens from the confirmation email sent to someone who signed up in the mobile app (signed link; no web sign-in needed).
Route::get('/email/verify-app/{id}/{hash}', \App\Http\Controllers\Auth\VerifyAppEmailController::class)
    ->whereNumber('id')->middleware(['signed', 'throttle:6,1'])->name('verification.app');

// Accepting an invitation to be a Platform Admin: a signed link from the email, opened by someone who may not have an account yet.
// The same page answers for a wrong, used, replaced, revoked or expired link. See docs/PLATFORM_ADMINS.md.
Route::get('/admin-invitation/{token}', [\App\Http\Controllers\AdminInvitationController::class, 'show'])
    ->where('token', '[a-f0-9]{64}')->middleware('throttle:30,1')->name('admin-invitation.show');
Route::post('/admin-invitation/{token}', [\App\Http\Controllers\AdminInvitationController::class, 'accept'])
    ->where('token', '[a-f0-9]{64}')->middleware('throttle:10,1')->name('admin-invitation.accept');

Route::middleware('guest')->group(function () {
    // New routes, not part of the original spec — the entry point for
    // choosing which kind of account to create (individual is hidden on
    // web for now; that flow is Flutter-only).
    Route::get('/register/type', [AccountTypeController::class, 'index'])->name('register.type');

    // Sign-up can be closed per kind of account (Platform Admin → Registration Control).
    Route::middleware('registration.open:organization')->group(function () {
        Route::get('/organizations/signup', [OrgSignupController::class, 'create'])->name('organizations.signup.create');
        Route::post('/organizations/signup', [OrgSignupController::class, 'store'])->name('organizations.signup.store');
    });

    Route::middleware('registration.open:counselor')->group(function () {
        Route::get('/counselors/signup', [CounselorSignupController::class, 'create'])->name('counselors.signup.create');
        Route::post('/counselors/signup', [CounselorSignupController::class, 'store'])->name('counselors.signup.store');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/organizations/{organization}/verify-domain', [OrgDomainVerificationController::class, 'show'])->name('organizations.signup.verify-domain');
    Route::post('/organizations/{organization}/verify-domain/continue', [OrgDomainVerificationController::class, 'continue'])->name('organizations.signup.verify-domain.continue');
    Route::post('/organizations/{organization}/domains/{domain}/check', [OrgDomainVerificationController::class, 'check'])->name('organizations.signup.verify-domain.check');

    Route::get('/organizations/{organization}/waiting-approval', [OrgApprovalStatusController::class, 'show'])->name('organizations.signup.waiting-approval');

    Route::get('/counselors/signup/verified', [CounselorSignupController::class, 'verified'])->name('counselors.signup.verified');

    Route::get('/counselors/signup/documents', [CounselorDocumentController::class, 'create'])->name('counselors.signup.documents');
    Route::post('/counselors/signup/documents', [CounselorDocumentController::class, 'store'])->name('counselors.signup.documents.store');

    Route::get('/counselors/signup/waiting-approval', [CounselorApprovalStatusController::class, 'show'])->name('counselors.signup.waiting-approval');
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

Route::middleware(['auth', 'verified', 'org.context', 'membership.active', 'org.status'])->group(function () {
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
        Route::get('/organizations/{organization}', [PlatformOrganizationController::class, 'show'])->whereNumber('organization')->name('organizations.show');
        Route::post('/organizations/{organization}/approve', [PlatformOrganizationDecisionController::class, 'approve'])->whereNumber('organization')->middleware('throttle:30,1')->name('organizations.approve');
        Route::post('/organizations/{organization}/reject', [PlatformOrganizationDecisionController::class, 'reject'])->whereNumber('organization')->middleware('throttle:30,1')->name('organizations.reject');
        Route::post('/organizations/{organization}/suspend', [PlatformOrganizationDecisionController::class, 'suspend'])->whereNumber('organization')->middleware('throttle:30,1')->name('organizations.suspend');
        Route::post('/organizations/{organization}/reactivate', [PlatformOrganizationDecisionController::class, 'reactivate'])->whereNumber('organization')->middleware('throttle:30,1')->name('organizations.reactivate');
        Route::patch('/organizations/{organization}/checklist', [PlatformOrganizationReviewController::class, 'checklist'])->whereNumber('organization')->middleware('throttle:120,1')->name('organizations.checklist');
        Route::post('/organizations/{organization}/notes', [PlatformOrganizationReviewController::class, 'storeNote'])->whereNumber('organization')->middleware('throttle:30,1')->name('organizations.notes.store');
        Route::post('/organizations/{organization}/dns/check', [PlatformOrganizationDnsController::class, 'check'])->whereNumber('organization')->middleware('throttle:20,1')->name('organizations.dns.check');
        Route::post('/organizations/{organization}/dns/remind', [PlatformOrganizationDnsController::class, 'remind'])->whereNumber('organization')->middleware('throttle:20,1')->name('organizations.dns.remind');
        Route::get('/counselors', [PlatformCounselorController::class, 'index'])->name('counselors');
        Route::get('/counselors/{counselor}', [PlatformCounselorController::class, 'show'])->whereNumber('counselor')->name('counselors.show');

        // LKM Directory: our snapshot of the LKM register, kept by hand. Records are added and changed, never deleted.
        Route::get('/lkm-directory', [PlatformLkmDirectoryController::class, 'index'])->name('lkm');
        Route::get('/lkm-directory/create', [PlatformLkmDirectoryController::class, 'create'])->name('lkm.create');
        Route::get('/lkm-directory/lookup', [PlatformLkmDirectoryController::class, 'lookup'])->middleware('throttle:120,1')->name('lkm.lookup');

        // CSV import (preview first, then confirm; creates and updates only, never deletes) and export.
        Route::get('/lkm-directory/import', [PlatformLkmImportController::class, 'show'])->name('lkm.import');
        Route::post('/lkm-directory/import', [PlatformLkmImportController::class, 'stage'])->middleware('throttle:10,1')->name('lkm.import.stage');
        Route::post('/lkm-directory/import/confirm', [PlatformLkmImportController::class, 'commit'])->middleware('throttle:10,1')->name('lkm.import.commit');
        Route::delete('/lkm-directory/import', [PlatformLkmImportController::class, 'cancel'])->middleware('throttle:30,1')->name('lkm.import.cancel');
        Route::get('/lkm-directory/import/errors', [PlatformLkmImportController::class, 'errors'])->middleware('throttle:30,1')->name('lkm.import.errors');
        Route::get('/lkm-directory/import/template', [PlatformLkmImportController::class, 'template'])->middleware('throttle:30,1')->name('lkm.import.template');
        // Counselors asking for their LKM record to be checked again. Evidence: no public URL, a short-lived signed link per admin.
        Route::get('/lkm-directory/reviews', [PlatformLkmReviewController::class, 'index'])->name('lkm.reviews');
        Route::post('/lkm-directory/reviews/{review}/approve', [PlatformLkmReviewController::class, 'approve'])->whereNumber('review')->middleware('throttle:30,1')->name('lkm.reviews.approve');
        Route::post('/lkm-directory/reviews/{review}/reject', [PlatformLkmReviewController::class, 'reject'])->whereNumber('review')->middleware('throttle:30,1')->name('lkm.reviews.reject');
        Route::get('/lkm-directory/reviews/{review}/evidence/open', [PlatformLkmReviewController::class, 'evidenceOpen'])->whereNumber('review')->middleware('throttle:60,1')->name('lkm.reviews.evidence.open');
        Route::get('/lkm-directory/reviews/{review}/evidence', [PlatformLkmReviewController::class, 'evidenceShow'])->whereNumber('review')->middleware('signed')->name('lkm.reviews.evidence');

        Route::get('/lkm-directory/export', [PlatformLkmImportController::class, 'export'])->middleware('throttle:10,1')->name('lkm.export');
        Route::post('/lkm-directory', [PlatformLkmDirectoryController::class, 'store'])->middleware('throttle:30,1')->name('lkm.store');
        Route::get('/lkm-directory/{record}', [PlatformLkmDirectoryController::class, 'show'])->whereNumber('record')->name('lkm.show');
        Route::put('/lkm-directory/{record}', [PlatformLkmDirectoryController::class, 'update'])->whereNumber('record')->middleware('throttle:60,1')->name('lkm.update');

        // Reviewing an independent counselor's application.
        Route::post('/counselors/{counselor}/approve', [PlatformCounselorReviewController::class, 'approve'])->whereNumber('counselor')->middleware('throttle:30,1')->name('counselors.approve');
        Route::post('/counselors/{counselor}/reject', [PlatformCounselorReviewController::class, 'reject'])->whereNumber('counselor')->middleware('throttle:30,1')->name('counselors.reject');
        Route::post('/counselors/{counselor}/lkm-recheck', [PlatformCounselorReviewController::class, 'recheckLkm'])->whereNumber('counselor')->middleware('throttle:20,1')->name('counselors.lkm-recheck');
        Route::patch('/counselors/{counselor}/documents/{document}/mark', [PlatformCounselorReviewController::class, 'markDocument'])->whereNumber(['counselor', 'document'])->middleware('throttle:60,1')->name('counselors.documents.mark');
        Route::patch('/counselors/{counselor}/checklist', [PlatformCounselorReviewController::class, 'checklist'])->whereNumber('counselor')->middleware('throttle:120,1')->name('counselors.checklist');
        Route::post('/counselors/{counselor}/notes', [PlatformCounselorReviewController::class, 'storeNote'])->whereNumber('counselor')->middleware('throttle:30,1')->name('counselors.notes.store');

        // Looking after an approved counselor (independent or organization).
        Route::post('/counselors/{counselor}/suspend', [PlatformCounselorStatusController::class, 'suspend'])->whereNumber('counselor')->middleware('throttle:30,1')->name('counselors.suspend');
        Route::post('/counselors/{counselor}/reactivate', [PlatformCounselorStatusController::class, 'reactivate'])->whereNumber('counselor')->middleware('throttle:30,1')->name('counselors.reactivate');
        Route::post('/counselors/{counselor}/renewal-reminder', [PlatformCounselorStatusController::class, 'remindRenewal'])->whereNumber('counselor')->middleware('throttle:20,1')->name('counselors.renewal-reminder');

        // DEVELOPMENT ONLY: stands in for the (not yet built) LKM review request. Not registered anywhere but local / testing.
        if (app()->environment(['local', 'testing'])) {
            Route::post('/counselors/{counselor}/dev/lkm-date', [PlatformCounselorStatusController::class, 'devSetLkmDate'])->whereNumber('counselor')->name('counselors.dev.lkm-date');
        }

        // Counselor documents: no public URL. The link endpoint hands out a short-lived signed URL; the signed route serves the file.
        Route::get('/counselors/{counselor}/documents/{document}/link', [PlatformCounselorDocumentController::class, 'link'])
            ->whereNumber(['counselor', 'document'])->middleware('throttle:60,1')->name('counselors.documents.link');
        Route::get('/counselor-documents/{document}', [PlatformCounselorDocumentController::class, 'show'])
            ->whereNumber('document')->middleware('signed')->name('counselor-documents.show');
        Route::get('/complaints', [PlatformComplaintController::class, 'index'])->name('complaints');
        Route::get('/complaints/{complaint}', [PlatformComplaintController::class, 'show'])->whereNumber('complaint')->name('complaints.show');
        Route::post('/complaints/{complaint}/status', [PlatformComplaintActionController::class, 'status'])->whereNumber('complaint')->middleware('throttle:30,1')->name('complaints.status');
        Route::post('/complaints/{complaint}/warn', [PlatformComplaintActionController::class, 'warn'])->whereNumber('complaint')->middleware('throttle:20,1')->name('complaints.warn');
        Route::post('/complaints/{complaint}/suspend-counselor', [PlatformComplaintActionController::class, 'suspendCounselor'])->whereNumber('complaint')->middleware('throttle:20,1')->name('complaints.suspend-counselor');
        Route::post('/complaints/{complaint}/notes', [PlatformComplaintController::class, 'storeNote'])->whereNumber('complaint')->middleware('throttle:30,1')->name('complaints.notes.store');

        // Platform Admins: who has access to this area. Admins are never deleted, only removed (the row and history stay).
        Route::get('/admins', [PlatformAdminController::class, 'index'])->name('admins');
        Route::post('/admins', [PlatformAdminController::class, 'store'])->middleware('throttle:10,1')->name('admins.store');
        Route::post('/admins/invitations/{invitation}/resend', [PlatformAdminController::class, 'resendInvitation'])->whereNumber('invitation')->middleware('throttle:10,1')->name('admins.invitations.resend');
        Route::post('/admins/invitations/{invitation}/revoke', [PlatformAdminController::class, 'revokeInvitation'])->whereNumber('invitation')->middleware('throttle:10,1')->name('admins.invitations.revoke');
        Route::post('/admins/{admin}/remove', [PlatformAdminController::class, 'remove'])->whereNumber('admin')->middleware('throttle:10,1')->name('admins.remove');

        // Pricing & Promotions: plans, promo codes and the commission setting. No payment is processed: these are records.
        Route::get('/pricing', [PlatformPricingController::class, 'index'])->name('pricing');
        Route::post('/pricing/plans', [PlatformPricingController::class, 'storePlan'])->middleware('throttle:20,1')->name('pricing.plans.store');
        Route::post('/pricing/plans/{plan}', [PlatformPricingController::class, 'updatePlan'])->whereNumber('plan')->middleware('throttle:30,1')->name('pricing.plans.update');
        Route::post('/pricing/commission', [PlatformPricingController::class, 'updateCommission'])->middleware('throttle:10,1')->name('pricing.commission');

        // Registration Control: close sign-ups (per kind of account or all), maintenance mode and the wording shown for them.
        Route::get('/registration', [PlatformRegistrationController::class, 'index'])->name('registration');
        Route::post('/registration/all', [PlatformRegistrationController::class, 'toggleAll'])->middleware('throttle:20,1')->name('registration.all');
        Route::post('/registration/maintenance', [PlatformRegistrationController::class, 'maintenance'])->middleware('throttle:20,1')->name('registration.maintenance');
        Route::put('/registration/maintenance/reopen', [PlatformRegistrationController::class, 'reopenTimeUpdate'])->middleware('throttle:20,1')->name('registration.maintenance.reopen');
        // Waitlist of the closed sign-ups: notify everyone once after reopening, remove one address, export. Never individuals.
        Route::post('/registration/waitlist/{type}/notify', [\App\Http\Controllers\Platform\PlatformWaitlistController::class, 'notify'])->whereIn('type', ['organization', 'counselor'])->middleware('throttle:10,1')->name('registration.waitlist.notify');
        Route::get('/registration/waitlist/{type}/export', [\App\Http\Controllers\Platform\PlatformWaitlistController::class, 'export'])->whereIn('type', ['organization', 'counselor'])->middleware('throttle:10,1')->name('registration.waitlist.export');
        Route::delete('/registration/waitlist/entries/{entry}', [\App\Http\Controllers\Platform\PlatformWaitlistController::class, 'remove'])->whereNumber('entry')->middleware('throttle:60,1')->name('registration.waitlist.remove');
        Route::put('/registration/messages', [PlatformRegistrationController::class, 'messages'])->middleware('throttle:20,1')->name('registration.messages');
        Route::post('/registration/{role}', [PlatformRegistrationController::class, 'toggle'])->where('role', '[a-z]+')->middleware('throttle:20,1')->name('registration.toggle');

        // Audit Log (read-only). The export route comes first so "export" is never read as an entry id.
        Route::get('/audit-log', [PlatformAuditLogController::class, 'index'])->name('audit-log');
        Route::get('/audit-log/export', [PlatformAuditLogController::class, 'export'])->middleware('throttle:5,1')->name('audit-log.export');
        Route::get('/audit-log/{entry}', [PlatformAuditLogController::class, 'show'])->whereNumber('entry')->name('audit-log.show');

        // Account (Settings > Account). Always acts on the signed-in user, never on an id from the URL.
        Route::prefix('account')->group(function () {
            Route::get('/', [AccountController::class, 'show'])->name('account');
            Route::patch('/', [AccountController::class, 'update'])->name('account.update');

            Route::get('/security', [AccountSecurityController::class, 'show'])->name('account.security');
            Route::put('/security/password', [AccountSecurityController::class, 'updatePassword'])->middleware('throttle:5,1')->name('account.security.password');
            Route::post('/security/sessions/revoke', [AccountSecurityController::class, 'revokeOtherSessions'])->middleware('throttle:5,1')->name('account.security.sessions.revoke');

            Route::prefix('/security/two-factor')->middleware('throttle:10,1')->group(function () {
                Route::post('/begin', [AccountTwoFactorController::class, 'begin'])->name('account.two-factor.begin');
                Route::post('/confirm', [AccountTwoFactorController::class, 'confirm'])->name('account.two-factor.confirm');
                Route::post('/cancel', [AccountTwoFactorController::class, 'cancel'])->name('account.two-factor.cancel');
                Route::post('/recovery-codes', [AccountTwoFactorController::class, 'regenerateRecoveryCodes'])->name('account.two-factor.recovery');
                Route::post('/disable', [AccountTwoFactorController::class, 'disable'])->name('account.two-factor.disable');
            });

            Route::get('/notifications', [AccountNotificationController::class, 'show'])->name('account.notifications');
            Route::patch('/notifications', [AccountNotificationController::class, 'update'])->middleware('throttle:60,1')->name('account.notifications.update');

            Route::post('/avatar',[AccountAvatarController::class, 'store'])->middleware('throttle:10,1')->name('account.avatar.store');
            Route::delete('/avatar', [AccountAvatarController::class, 'destroy'])->name('account.avatar.destroy');

            Route::post('/email', [AccountEmailController::class, 'store'])->middleware('throttle:5,1')->name('account.email.store');
            Route::delete('/email', [AccountEmailController::class, 'destroy'])->name('account.email.destroy');
            Route::get('/email/verify/{user}/{hash}', [AccountEmailController::class, 'verify'])
                ->middleware(['signed', 'throttle:10,1'])->whereNumber('user')->name('account.email.verify');
        });
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/counselors', [CounselorSearchController::class, 'index'])->name('counselors.index');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');

    // Original Breeze account endpoints. Platform Admin accounts are locked out of them (see LegacyAccountGuard).
    Route::middleware('legacy.account')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

require __DIR__.'/auth.php';
