<?php

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\User;

/*
 * Audit log catalogue. Every audit entry uses an action key from `actions` — logging an unknown key throws,
 * so a typo is caught in tests instead of silently writing an entry nobody can filter.
 *
 * Add a new event when you build the page that produces it:
 *   'area.event' => ['category' => ..., 'badge' => ..., 'label' => ...]
 */
return [

    // Entries from these categories are hidden on the Audit Log page unless "Sign-in activity" is switched on.
    'categories' => [
        'organizations' => ['label' => 'Organizations'],
        'members' => ['label' => 'Members & counselors (organization admins)'],
        'counselors' => ['label' => 'Counselors'],
        'lkm' => ['label' => 'LKM records'],
        'complaints' => ['label' => 'Complaints'],
        'moderation' => ['label' => 'Moderation'],
        'settings' => ['label' => 'Platform settings'],
        'pricing' => ['label' => 'Pricing & promo codes'],
        'admins' => ['label' => 'Platform admins'],
        'security' => ['label' => 'Account security'],
        'account' => ['label' => 'Account'],
        'system' => ['label' => 'System jobs'],
        'audit' => ['label' => 'Audit log'],
        'auth' => ['label' => 'Sign-in activity', 'hidden_by_default' => true],
        'other' => ['label' => 'Other'],
    ],

    // badge: created | approved | rejected | suspended | reactivated | updated | deleted | exported | security | system
    'actions' => [
        // Organizations (Platform Admin)
        'organization.approved' => ['category' => 'organizations', 'badge' => 'approved', 'label' => 'Organization approved'],
        'organization.rejected' => ['category' => 'organizations', 'badge' => 'rejected', 'label' => 'Organization rejected'],
        'organization.suspended' => ['category' => 'organizations', 'badge' => 'suspended', 'label' => 'Organization suspended'],
        'organization.reactivated' => ['category' => 'organizations', 'badge' => 'reactivated', 'label' => 'Organization reactivated'],
        'organization.note_added' => ['category' => 'organizations', 'badge' => 'created', 'label' => 'Internal note added'],
        'organization.dns_checked' => ['category' => 'organizations', 'badge' => 'updated', 'label' => 'DNS checked'],
        'organization.dns_verified' => ['category' => 'organizations', 'badge' => 'system', 'label' => 'Domain verified by system'],
        'organization.dns_reminder_sent' => ['category' => 'organizations', 'badge' => 'updated', 'label' => 'DNS reminder emailed'],

        // Organization admins acting on their members / counselors (always carry the organization id)
        'member.added' => ['category' => 'members', 'badge' => 'created', 'label' => 'Member added'],
        'member.removed' => ['category' => 'members', 'badge' => 'deleted', 'label' => 'Member removed'],
        'member.role_changed' => ['category' => 'members', 'badge' => 'updated', 'label' => 'Member role changed'],
        'member.offboarded' => ['category' => 'members', 'badge' => 'updated', 'label' => 'Member offboarded'],
        'org_counselor.added' => ['category' => 'members', 'badge' => 'created', 'label' => 'Counselor added to organization'],
        'org_counselor.removed' => ['category' => 'members', 'badge' => 'deleted', 'label' => 'Counselor removed from organization'],

        // Counselors (Platform Admin)
        'counselor.approved' => ['category' => 'counselors', 'badge' => 'approved', 'label' => 'Counselor approved'],
        'counselor.rejected' => ['category' => 'counselors', 'badge' => 'rejected', 'label' => 'Counselor rejected'],
        'counselor.suspended' => ['category' => 'counselors', 'badge' => 'suspended', 'label' => 'Counselor suspended'],
        'counselor.reactivated' => ['category' => 'counselors', 'badge' => 'reactivated', 'label' => 'Counselor reactivated'],
        'counselor.note_added' => ['category' => 'counselors', 'badge' => 'created', 'label' => 'Internal note added'],
        // Written by the (not yet built) scheduled PA-expiry check — always the "system" actor.
        'counselor.pa_expired_suspended' => ['category' => 'counselors', 'badge' => 'suspended', 'label' => 'Suspended automatically: practising certificate expired'],

        // LKM records
        'lkm.record_created' => ['category' => 'lkm', 'badge' => 'created', 'label' => 'LKM record added'],
        'lkm.record_updated' => ['category' => 'lkm', 'badge' => 'updated', 'label' => 'LKM record updated'],
        'lkm.record_deleted' => ['category' => 'lkm', 'badge' => 'deleted', 'label' => 'LKM record removed'],
        'lkm.review_requested' => ['category' => 'lkm', 'badge' => 'created', 'label' => 'LKM review requested'],
        'lkm.review_resolved' => ['category' => 'lkm', 'badge' => 'updated', 'label' => 'LKM review resolved'],

        // Complaints (reference IDs only — never the complaint text)
        'complaint.status_changed' => ['category' => 'complaints', 'badge' => 'updated', 'label' => 'Complaint status changed'],
        'complaint.warning_issued' => ['category' => 'complaints', 'badge' => 'security', 'label' => 'Warning issued'],
        'complaint.counselor_suspended' => ['category' => 'complaints', 'badge' => 'suspended', 'label' => 'Counselor suspended after complaint'],

        // Moderation
        'moderation.report_dismissed' => ['category' => 'moderation', 'badge' => 'updated', 'label' => 'Report dismissed'],
        'moderation.post_removed' => ['category' => 'moderation', 'badge' => 'deleted', 'label' => 'Post removed'],
        'moderation.user_warned' => ['category' => 'moderation', 'badge' => 'security', 'label' => 'User warned'],

        // Platform settings
        'settings.registration_toggled' => ['category' => 'settings', 'badge' => 'updated', 'label' => 'Registration switched'],
        'settings.maintenance_toggled' => ['category' => 'settings', 'badge' => 'updated', 'label' => 'Maintenance mode switched'],

        // Pricing
        'pricing.plan_created' => ['category' => 'pricing', 'badge' => 'created', 'label' => 'Plan created'],
        'pricing.plan_updated' => ['category' => 'pricing', 'badge' => 'updated', 'label' => 'Plan updated'],
        'pricing.plan_deleted' => ['category' => 'pricing', 'badge' => 'deleted', 'label' => 'Plan deleted'],
        'pricing.promo_created' => ['category' => 'pricing', 'badge' => 'created', 'label' => 'Promo code created'],
        'pricing.promo_updated' => ['category' => 'pricing', 'badge' => 'updated', 'label' => 'Promo code updated'],
        'pricing.promo_deleted' => ['category' => 'pricing', 'badge' => 'deleted', 'label' => 'Promo code deleted'],

        // Platform admins
        'admin.invited' => ['category' => 'admins', 'badge' => 'created', 'label' => 'Platform admin invited'],
        'admin.invite_revoked' => ['category' => 'admins', 'badge' => 'deleted', 'label' => 'Invite revoked'],
        'admin.role_changed' => ['category' => 'admins', 'badge' => 'updated', 'label' => 'Admin role changed'],
        'admin.removed' => ['category' => 'admins', 'badge' => 'deleted', 'label' => 'Platform admin removed'],

        // The signed-in user's own account
        'account.password_changed' => ['category' => 'security', 'badge' => 'security', 'label' => 'Password changed'],
        'account.email_change_requested' => ['category' => 'security', 'badge' => 'security', 'label' => 'Email change requested'],
        'account.email_changed' => ['category' => 'security', 'badge' => 'security', 'label' => 'Email address changed'],
        'account.two_factor_enabled' => ['category' => 'security', 'badge' => 'security', 'label' => 'Two-factor authentication turned on'],
        'account.two_factor_disabled' => ['category' => 'security', 'badge' => 'security', 'label' => 'Two-factor authentication turned off'],
        'account.two_factor_failed' => ['category' => 'security', 'badge' => 'security', 'label' => 'Failed two-factor attempt'],
        'account.recovery_code_used' => ['category' => 'security', 'badge' => 'security', 'label' => 'Recovery code used to sign in'],
        'account.recovery_codes_regenerated' => ['category' => 'security', 'badge' => 'security', 'label' => 'Recovery codes regenerated'],
        'account.other_sessions_revoked' => ['category' => 'security', 'badge' => 'security', 'label' => 'Signed out other sessions'],
        'account.avatar_changed' => ['category' => 'account', 'badge' => 'updated', 'label' => 'Profile photo changed'],

        // Sign-in activity (hidden on the Audit Log page by default)
        'auth.login' => ['category' => 'auth', 'badge' => 'security', 'label' => 'Signed in'],
        'auth.login_failed' => ['category' => 'auth', 'badge' => 'security', 'label' => 'Failed sign-in attempt'],

        // Scheduled / queued jobs — the actor is always "system"
        'system.booking_requests_expired' => ['category' => 'system', 'badge' => 'system', 'label' => 'Booking requests expired'],
        'system.chat_purged' => ['category' => 'system', 'badge' => 'system', 'label' => 'Chat data purged'],

        // The audit log itself
        'audit.exported' => ['category' => 'audit', 'badge' => 'exported', 'label' => 'Audit log exported'],
    ],

    // Model class => short key stored in audit_logs.target_type. Unknown models fall back to snake_case of the class name.
    'target_types' => [
        Organization::class => 'organization',
        User::class => 'user',
        CounselorProfile::class => 'counselor',
        Complaint::class => 'complaint',
        Booking::class => 'booking',
    ],

    // Roles whose name + role are copied into the entry. Everyone else is stored as a bare user id
    // (the app promises personal data is removed when an account is deleted).
    'staff_roles' => ['platform_admin', 'org_admin', 'counselor'],

    // Metadata keys removed before saving (case-insensitive, matched anywhere in the key).
    'sensitive_key_patterns' => [
        'password', 'passwd', 'token', 'secret', 'recovery', 'two_factor', 'twofactor', '2fa', 'otp',
        'authorization', 'api_key', 'apikey', 'cookie', 'credential',
    ],

    // Keys that would carry chat / message / complaint text. Audit entries hold reference IDs only.
    'content_key_patterns' => [
        'message', 'content', 'body', 'text', 'chat', 'transcript', 'description', 'complaint_text',
    ],

    'metadata_max_string' => 2000,
    'metadata_max_depth' => 5,

    // Failed sign-ins written per IP per minute. More than this are counted but not stored,
    // so a password-guessing attack cannot flood the table.
    'login_failed_per_ip_per_minute' => 10,

    // Audit Log page
    'per_page_options' => [25, 50, 100],
    'export_max_rows' => 10000,
    'export_chunk' => 500,
];
