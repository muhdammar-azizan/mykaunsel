<?php

use App\Models\AdminInvitation;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
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
        'waitlist' => ['label' => 'Waitlist'],
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
        'counselor.resubmitted' => ['category' => 'counselors', 'badge' => 'updated', 'label' => 'Counselor resubmitted documents'],
        'counselor.document_flagged' => ['category' => 'counselors', 'badge' => 'updated', 'label' => 'Document marked'],
        'counselor.document_viewed' => ['category' => 'counselors', 'badge' => 'security', 'label' => 'Document viewed'],
        'counselor.document_purged' => ['category' => 'counselors', 'badge' => 'deleted', 'label' => 'Document removed (retention)'],
        'counselor.lkm_rechecked' => ['category' => 'counselors', 'badge' => 'updated', 'label' => 'LKM check re-run'],
        'counselor.kb_duplicate_flagged' => ['category' => 'counselors', 'badge' => 'security', 'label' => 'Duplicate KB registration attempt'],
        'counselor.renewal_reminder_sent' => ['category' => 'counselors', 'badge' => 'updated', 'label' => 'PA renewal reminder sent'],
        // Written by the daily PA check (counselors:check-pa-expiry) — always the "system" actor.
        'counselor.pa_expired_suspended' => ['category' => 'counselors', 'badge' => 'suspended', 'label' => 'Suspended automatically: practising certificate expired'],

        // LKM records
        'lkm.record_created' => ['category' => 'lkm', 'badge' => 'created', 'label' => 'LKM record added'],
        'lkm.record_updated' => ['category' => 'lkm', 'badge' => 'updated', 'label' => 'LKM record updated'],
        // One entry per import, with counts only (never the rows themselves).
        'lkm.records_imported' => ['category' => 'lkm', 'badge' => 'created', 'label' => 'LKM records imported (CSV)'],
        'lkm.records_exported' => ['category' => 'lkm', 'badge' => 'exported', 'label' => 'LKM records exported (CSV)'],
        // A counselor's request to re-check their LKM record (the target is the counselor, so it shows in their activity too).
        'lkm.review_requested' => ['category' => 'lkm', 'badge' => 'created', 'label' => 'LKM review requested'],
        'lkm.review_approved' => ['category' => 'lkm', 'badge' => 'approved', 'label' => 'LKM review approved'],
        'lkm.review_rejected' => ['category' => 'lkm', 'badge' => 'rejected', 'label' => 'LKM review rejected'],
        'lkm.evidence_viewed' => ['category' => 'lkm', 'badge' => 'security', 'label' => 'LKM review evidence viewed'],
        'lkm.evidence_purged' => ['category' => 'lkm', 'badge' => 'deleted', 'label' => 'LKM review evidence removed (retention)'],

        // Waitlist for closed sign-ups. Counts and record ids only: NEVER an email address. Sign-ups and opt-outs are not audited.
        'waitlist.notified' => ['category' => 'waitlist', 'badge' => 'updated', 'label' => 'Waitlist notified'],
        'waitlist.exported' => ['category' => 'waitlist', 'badge' => 'exported', 'label' => 'Waitlist exported'],
        'waitlist.entry_removed' => ['category' => 'waitlist', 'badge' => 'deleted', 'label' => 'Waitlist address removed'],
        // Written by the daily retention job (waitlist:purge): always the "system" actor.
        'waitlist.purged' => ['category' => 'waitlist', 'badge' => 'deleted', 'label' => 'Waitlist addresses removed (retention)'],

        // Complaints (reference IDs only — never the complaint text)
        'complaint.note_added' => ['category' => 'complaints', 'badge' => 'created', 'label' => 'Internal note added'],
        'complaint.status_changed' => ['category' => 'complaints', 'badge' => 'updated', 'label' => 'Complaint status changed'],
        'complaint.warning_issued' => ['category' => 'complaints', 'badge' => 'security', 'label' => 'Warning issued'],
        // Written by the daily retention job (complaints:purge-text): always the "system" actor, counts only.
        'complaint.text_purged' => ['category' => 'complaints', 'badge' => 'deleted', 'label' => 'Complaint text erased (retention)'],
        'complaint.counselor_suspended' => ['category' => 'complaints', 'badge' => 'suspended', 'label' => 'Counselor suspended after complaint'],

        // Moderation
        'moderation.report_dismissed' => ['category' => 'moderation', 'badge' => 'updated', 'label' => 'Report dismissed'],
        'moderation.post_removed' => ['category' => 'moderation', 'badge' => 'deleted', 'label' => 'Post removed'],
        'moderation.user_warned' => ['category' => 'moderation', 'badge' => 'security', 'label' => 'User warned'],

        // Platform settings
        'settings.registration_toggled' => ['category' => 'settings', 'badge' => 'updated', 'label' => 'Registration switched'],
        'settings.maintenance_toggled' => ['category' => 'settings', 'badge' => 'updated', 'label' => 'Maintenance mode switched'],
        'settings.maintenance_reopen_scheduled' => ['category' => 'settings', 'badge' => 'updated', 'label' => 'Maintenance reopen time changed'],
        // Written by the scheduler (maintenance:reopen-due) when the set time arrives: always the "system" actor.
        'settings.maintenance_auto_reopened' => ['category' => 'settings', 'badge' => 'system', 'label' => 'Maintenance ended automatically'],
        'settings.message_updated' => ['category' => 'settings', 'badge' => 'updated', 'label' => 'Notice wording edited'],

        // Pricing & Promotions (no payment: these record plans, promo codes and who is on which plan).
        // Metadata holds ids and numbers only. Plans are never deleted, so there is no plan.deleted.
        'plan.created' => ['category' => 'pricing', 'badge' => 'created', 'label' => 'Plan created'],
        'plan.updated' => ['category' => 'pricing', 'badge' => 'updated', 'label' => 'Plan updated'],
        'plan.activated' => ['category' => 'pricing', 'badge' => 'reactivated', 'label' => 'Plan activated'],
        'plan.deactivated' => ['category' => 'pricing', 'badge' => 'suspended', 'label' => 'Plan deactivated'],
        'promo.created' => ['category' => 'pricing', 'badge' => 'created', 'label' => 'Promo code created'],
        'promo.updated' => ['category' => 'pricing', 'badge' => 'updated', 'label' => 'Promo code updated'],
        'promo.enabled' => ['category' => 'pricing', 'badge' => 'reactivated', 'label' => 'Promo code enabled'],
        'promo.disabled' => ['category' => 'pricing', 'badge' => 'suspended', 'label' => 'Promo code disabled'],
        'promo.deleted' => ['category' => 'pricing', 'badge' => 'deleted', 'label' => 'Promo code deleted'],
        'promo.redeemed' => ['category' => 'pricing', 'badge' => 'approved', 'label' => 'Promo code redeemed'],
        'commission.updated' => ['category' => 'pricing', 'badge' => 'updated', 'label' => 'Commission updated'],
        'organization.plan_assigned' => ['category' => 'organizations', 'badge' => 'updated', 'label' => 'Plan assigned to organization'],
        'organization.plan_changed' => ['category' => 'organizations', 'badge' => 'updated', 'label' => 'Organization plan changed'],
        // Platform admins
        'admin.invited' => ['category' => 'admins', 'badge' => 'created', 'label' => 'Platform admin invited'],
        'admin.invite_resent' => ['category' => 'admins', 'badge' => 'updated', 'label' => 'Platform admin invitation sent again'],
        'admin.invite_accepted' => ['category' => 'admins', 'badge' => 'approved', 'label' => 'Platform admin invitation accepted'],
        // Written by the scheduler (admin-invitations:purge): old invitations lose their name and email. A count only, never a name or email.
        'admin.invitations_purged' => ['category' => 'admins', 'badge' => 'system', 'label' => 'Old invitation details erased'],
        'admin.invite_revoked' => ['category' => 'admins', 'badge' => 'deleted', 'label' => 'Platform admin invitation revoked'],
        'admin.role_changed' => ['category' => 'admins', 'badge' => 'updated', 'label' => 'Admin role changed'],
        'admin.created_from_console' => ['category' => 'admins', 'badge' => 'created', 'label' => 'Platform admin created from the command line'],
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
    // Action keys that older entries in the database may still carry. They are NOT in `actions`, so nothing can write them any
    // more, but the Audit Log still shows them with a proper label. Old rows are never rewritten.
    'legacy_actions' => [
        'pricing.plan_created' => ['category' => 'pricing', 'badge' => 'created', 'label' => 'Plan created'],
        'pricing.plan_updated' => ['category' => 'pricing', 'badge' => 'updated', 'label' => 'Plan updated'],
        'pricing.plan_deleted' => ['category' => 'pricing', 'badge' => 'deleted', 'label' => 'Plan deleted'],
        'pricing.promo_created' => ['category' => 'pricing', 'badge' => 'created', 'label' => 'Promo code created'],
        'pricing.promo_updated' => ['category' => 'pricing', 'badge' => 'updated', 'label' => 'Promo code updated'],
        'pricing.promo_deleted' => ['category' => 'pricing', 'badge' => 'deleted', 'label' => 'Promo code deleted'],
    ],

    'target_types' => [
        Organization::class => 'organization',
        User::class => 'user',
        CounselorProfile::class => 'counselor',
        LkmDirectorySnapshot::class => 'lkm_record',
        Complaint::class => 'complaint',
        Booking::class => 'booking',
        AdminInvitation::class => 'admin_invitation',
        SubscriptionPlan::class => 'plan',
        PromoCode::class => 'promo_code',
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
