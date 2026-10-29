<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\User;
use App\Support\CounselorAttention;
use App\Support\CounselorListFilters;
use App\Support\WaitingTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Everything the Platform Admin Dashboard shows, worked out in ONE place by composing the methods the owning pages already use.
 * It does not repeat their logic: if a number here and the same number on its page ever differ, one of them is wrong.
 *
 * - Pending organizations = the Organizations "Pending" tab (Organization::awaitingReview()); skipped DNS = its "Skipped" tab.
 * - Pending counselors = the Counselors "Pending" tab (CounselorListFilters::forTab()).
 * - Active complaints = the Complaints "Open" tab + "Under review" tab.
 * - Platform stats = AnalyticsService (approved organizations and counselors include the suspended ones).
 * - Recent approvals = the audit log only, never back-filled.
 * - The banner = Registration Control (PlatformSettings) and the waitlist (WaitlistService).
 *
 * Nothing is kept between requests (no cache): an admin who approves, suspends or switches something sees the new number at once.
 * Names and reference numbers only: no complaint text, document names, notes or chat ever pass through here (the complaint query
 * does not even select the description).
 */
class DashboardService
{
    /** At most this many "Needs your attention" items are returned; the rest are only counted. */
    public const ATTENTION_LIMIT = 8;

    /** Shown in "Recent approvals". */
    public const APPROVALS_LIMIT = 5;

    /** The audit actions "Recent approvals" reads. */
    public const APPROVAL_ACTIONS = ['organization.approved', 'counselor.approved'];

    private const TONE_RANK = ['red' => 0, 'amber' => 1, 'ok' => 2];

    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly PlatformSettings $settings,
        private readonly WaitlistService $waitlist,
        private readonly PricingStats $pricing,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $admin): array
    {
        $timezone = $admin->display_timezone;

        return [
            'greeting' => $this->greeting($admin),
            'banner' => $this->banner($timezone),
            'cards' => $this->cards(),
            'attention' => $this->attention(),
            'stats' => $this->stats($timezone),
            'approvals' => $this->approvals($timezone),
        ];
    }

    // =============================================================================================================
    //  Greeting
    // =============================================================================================================

    /** @return array{text: string, first_name: string, timezone: string} the time of day is the admin's own (Account page) */
    public function greeting(User $admin, ?CarbonImmutable $now = null): array
    {
        $timezone = $admin->display_timezone;
        $hour = ($now ?? CarbonImmutable::now())->setTimezone($timezone)->hour;

        return [
            'text' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
            'first_name' => (string) Str::of($admin->name)->trim()->before(' '),
            'timezone' => $timezone,
        ];
    }

    // =============================================================================================================
    //  The four cards (each number is the count of its page's tab)
    // =============================================================================================================

    public function pendingOrganizations(): int
    {
        return Organization::query()->applicants()->awaitingReview()->count();
    }

    public function skippedDns(): int
    {
        return Organization::query()->applicants()->skippedVerification()->count();
    }

    public function pendingCounselors(): int
    {
        return CounselorListFilters::forTab(CounselorProfile::query(), 'pending')->count();
    }

    /** @return array{open: int, under_review: int, total: int} */
    public function activeComplaints(): array
    {
        $open = Complaint::query()->withStatus(ComplaintStatus::Open)->count();
        $underReview = Complaint::query()->withStatus(ComplaintStatus::UnderReview)->count();

        return ['open' => $open, 'under_review' => $underReview, 'total' => $open + $underReview];
    }

    /** @return array<string, array<string, mixed>> */
    public function cards(): array
    {
        $complaints = $this->activeComplaints();

        return [
            'pending_organizations' => ['count' => $this->pendingOrganizations(), 'url' => route('platform.organizations', ['tab' => 'pending'])],
            'pending_counselors' => ['count' => $this->pendingCounselors(), 'url' => route('platform.counselors', ['tab' => 'pending'])],
            'active_complaints' => ['count' => $complaints['total'], 'open' => $complaints['open'], 'under_review' => $complaints['under_review'], 'url' => route('platform.complaints', ['tab' => 'open'])],
            'skipped_dns' => ['count' => $this->skippedDns(), 'url' => route('platform.organizations', ['tab' => 'skipped'])],
        ];
    }

    // =============================================================================================================
    //  Needs your attention
    // =============================================================================================================

    /**
     * The most urgent items first: red before amber before the rest, the longest wait first, at most ATTENTION_LIMIT of them. `total`
     * counts every eligible item and `more_by_destination` says where the ones not shown can be found (one line per page).
     *
     * Each source loads at most ATTENTION_LIMIT rows, already oldest first, so the final cut can never miss a more urgent row, and the
     * number of queries does not grow with the data.
     *
     * @return array{items: list<array<string, mixed>>, total: int, more: int, more_by_destination: list<array{label: string, url: string, count: int}>}
     */
    public function attention(): array
    {
        $limit = self::ATTENTION_LIMIT;
        $cutoff = now()->subDays(WaitingTime::AMBER_DAYS)->format('Y-m-d H:i:s');
        $sources = [];

        // Organizations waiting for a normal review for 5 days or more.
        $waiting = fn (): Builder => Organization::query()->applicants()->awaitingReview()->whereRaw('COALESCE(submitted_at, created_at) <= ?', [$cutoff]);
        $sources['organization_waiting'] = $this->source(
            $waiting()->orderByRaw('COALESCE(submitted_at, created_at) asc')->orderBy('id')->limit($limit)->get(['id', 'name', 'submitted_at', 'created_at'])
                ->map(fn (Organization $org) => $this->item('organization_waiting', "organization:{$org->id}", $org->waitingDays(), $org->name.' — organization awaiting approval', route('platform.organizations.show', $org))),
            $waiting()->count(), 'Organizations waiting for review', route('platform.organizations', ['tab' => 'pending']),
        );

        // Organizations that skipped domain verification and are still pending (any age: a person has to check them by hand).
        $skipped = fn (): Builder => Organization::query()->applicants()->skippedVerification();
        $sources['organization_dns'] = $this->source(
            $skipped()->orderByRaw('COALESCE(submitted_at, created_at) asc')->orderBy('id')->limit($limit)->get(['id', 'name', 'submitted_at', 'created_at'])
                ->map(fn (Organization $org) => $this->item('organization_dns', "organization-dns:{$org->id}", $org->waitingDays(), $org->name.' — domain not verified, needs a manual check', route('platform.organizations.show', $org))),
            $skipped()->count(), 'Organizations that skipped domain verification', route('platform.organizations', ['tab' => 'skipped']),
        );

        // Pending counselors: waiting 5 days or more, or whose LKM record no longer backs them up. One row per counselor.
        $pending = fn (): Builder => CounselorAttention::pending()->where(function (Builder $q) use ($cutoff) {
            $q->whereRaw('COALESCE(counselor_profiles.submitted_at, counselor_profiles.created_at) <= ?', [$cutoff])
                ->orWhere(fn (Builder $w) => $w->lkmUnverified());
        });
        $profiles = $pending()->with('user:id,name')
            ->orderByRaw('COALESCE(counselor_profiles.submitted_at, counselor_profiles.created_at) asc')->orderBy('counselor_profiles.id')
            ->limit($limit)->get(['counselor_profiles.id', 'counselor_profiles.user_id', 'counselor_profiles.kb_number', 'counselor_profiles.submitted_at', 'counselor_profiles.created_at']);
        $unverified = $profiles->isEmpty() ? [] : CounselorAttention::lkmNotVerified()->whereIn('counselor_profiles.id', $profiles->pluck('id'))->pluck('counselor_profiles.id')->all();
        $sources['counselor_pending'] = $this->source(
            $profiles->map(function (CounselorProfile $profile) use ($unverified) {
                $name = $profile->user->name ?? 'Unknown';
                $lkm = in_array($profile->id, $unverified, true);

                return $this->item($lkm ? 'counselor_lkm' : 'counselor_waiting', "counselor:{$profile->id}", $profile->waitingDays(),
                    $lkm ? "{$name} ({$profile->kb_number}) — LKM number could not be verified" : "{$name} — credentials awaiting verification",
                    route('platform.counselors.show', $profile));
            }),
            $pending()->count(), 'Counselors waiting for review', route('platform.counselors', ['tab' => 'pending']),
        );

        // Accounts someone tried to register the KB number of again (any status). The last attempt decides how old it is.
        $duplicates = CounselorAttention::duplicateKb()->with('user:id,name')->withMax('kbAttempts', 'created_at')
            ->orderByDesc('kb_attempts_max_created_at')->orderBy('counselor_profiles.id')->limit($limit)->get();
        $sources['duplicate_kb'] = $this->source(
            $duplicates->map(function (CounselorProfile $profile) {
                $last = CarbonImmutable::parse($profile->kb_attempts_max_created_at);

                return $this->item('counselor_duplicate_kb', "duplicate-kb:{$profile->id}", (int) $last->diffInDays(now()),
                    ($profile->user->name ?? 'Unknown').' ('.$profile->kb_number.') — duplicate KB registration attempt',
                    route('platform.counselors.show', $profile), 'amber', 'Last attempt '.($last->isToday() ? 'today' : $last->diffForHumans()));
            }),
            CounselorAttention::duplicateKb()->count(), 'Counselors with a duplicate KB attempt', route('platform.counselors', ['flagged' => 1]),
        );

        // Complaints still open (not yet taken up). Only the reference number: the text is not even selected.
        $open = fn (): Builder => Complaint::query()->withStatus(ComplaintStatus::Open);
        $sources['complaint_open'] = $this->source(
            $open()->orderBy('created_at')->orderBy('id')->limit($limit)->get(['id', 'created_at'])
                ->map(fn (Complaint $complaint) => $this->item('complaint_open', "complaint:{$complaint->id}", (int) $complaint->created_at->diffInDays(now()), "Complaint #{$complaint->id} — open, waiting for a response", route('platform.complaints.show', $complaint))),
            $open()->count(), 'Open complaints', route('platform.complaints', ['tab' => 'open']),
        );

        // Two summaries (one line each, no names): practising certificates and plans about to end.
        $expiring = $this->counselorsWithPaExpiringSoon();
        $sources['pa_expiring'] = $this->source(
            collect($expiring > 0 ? [$this->item('counselors_pa_expiring', 'pa-expiring', null, $expiring.' '.Str::plural('counselor', $expiring).' with a practising certificate (PA) expiring within '.CounselorProfile::PA_EXPIRING_DAYS.' days', route('platform.counselors', ['tab' => 'active', 'pa' => 'expiring']), 'amber')] : []),
            $expiring, 'Counselors with PA expiring soon', route('platform.counselors', ['tab' => 'active', 'pa' => 'expiring']),
        );

        $ending = $this->pricing->subscriptionsEndingWithin(30);
        $sources['subscriptions_ending'] = $this->source(
            collect($ending > 0 ? [$this->item('subscriptions_ending', 'subscriptions-ending', null, $ending.' '.Str::plural('organization', $ending).' with a plan ending within 30 days', route('platform.organizations', ['tab' => 'approved']), 'amber')] : []),
            $ending, 'Organizations with a plan ending soon', route('platform.organizations', ['tab' => 'approved']),
        );

        $all = collect($sources)->flatMap(fn (array $s) => $s['items'])->all();
        usort($all, fn (array $a, array $b) => [self::TONE_RANK[$a['tone']], -($a['days'] ?? -1), $a['key']] <=> [self::TONE_RANK[$b['tone']], -($b['days'] ?? -1), $b['key']]);

        $shown = array_slice($all, 0, $limit);
        $shownKeys = array_column($shown, 'key');
        $total = (int) collect($sources)->sum('total');
        $more = [];

        foreach ($sources as $source) {
            $hidden = $source['total'] - collect($source['items'])->filter(fn (array $i) => in_array($i['key'], $shownKeys, true))->count();

            if ($hidden > 0) {
                $more[] = ['label' => $source['label'], 'url' => $source['url'], 'count' => $hidden];
            }
        }

        return ['items' => $shown, 'total' => $total, 'more' => max(0, $total - count($shown)), 'more_by_destination' => $more];
    }

    /** Active (approved) counselors whose practising certificate runs out within CounselorProfile::PA_EXPIRING_DAYS days: the Counselors page filter pa=expiring on its Active tab. */
    public function counselorsWithPaExpiringSoon(): int
    {
        return CounselorListFilters::forTab(CounselorProfile::query(), 'active')->paStatus('expiring')->count();
    }

    /** @return array{items: list<array<string, mixed>>, total: int, label: string, url: string} */
    private function source($items, int $total, string $label, string $url): array
    {
        return ['items' => collect($items)->values()->all(), 'total' => $total, 'label' => $label, 'url' => $url];
    }

    /** @return array<string, mixed> */
    private function item(string $kind, string $key, ?int $days, string $text, string $url, ?string $tone = null, ?string $detail = null): array
    {
        return [
            'kind' => $kind,
            'key' => $key,
            'tone' => $tone ?? ($days === null ? 'ok' : WaitingTime::tone($days)),
            'days' => $days,
            'text' => $text,
            'detail' => $detail ?? ($days === null ? null : 'Waiting '.$days.' '.Str::plural('day', $days)),
            'url' => $url,
        ];
    }

    // =============================================================================================================
    //  Platform stats
    // =============================================================================================================

    /**
     * Totals from AnalyticsService, so this widget and the Analytics page agree. `sessions` is "completed sessions this month, by
     * session date" in the admin's timezone; `has_data` false means the page says "No session data yet".
     *
     * @return array{organizations: int, counselors: int, members: int, sessions: array{count: int, has_data: bool}}
     */
    public function stats(string $timezone): array
    {
        return [
            'organizations' => $this->analytics->totalOrganizations(),
            'counselors' => $this->analytics->totalCounselors(),
            'members' => $this->analytics->activeMembers(),
            'sessions' => ['count' => $this->analytics->completedSessionsThisMonth($timezone), 'has_data' => $this->analytics->anySessionOutcomes()],
        ];
    }

    // =============================================================================================================
    //  Recent approvals (the audit log only)
    // =============================================================================================================

    /**
     * The latest approvals, read ONLY from the audit log with the name and the actor as they were saved at the time. Nothing is
     * back-filled: approvals made before the log existed do not appear.
     *
     * @return array{rows: list<array{name: string, type: string, by: string, at: CarbonImmutable}>, view_all_url: string}
     */
    public function approvals(string $timezone): array
    {
        $rows = AuditLog::query()
            ->whereIn('action', self::APPROVAL_ACTIONS)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::APPROVALS_LIMIT)
            ->get(['id', 'action', 'actor_type', 'actor_name', 'target_id', 'target_name', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'name' => $log->target_name ?? ($log->target_id ? '#'.$log->target_id : 'Unknown'),
                'type' => $log->action === 'counselor.approved' ? 'Counselor' : 'Organization',
                'by' => $log->isSystem() ? 'System' : ($log->actor_name ?? 'Unknown'),
                'at' => CarbonImmutable::instance($log->created_at)->setTimezone($timezone),
            ])->all();

        return ['rows' => $rows, 'view_all_url' => route('platform.audit-log', ['actions' => implode(',', self::APPROVAL_ACTIONS)])];
    }

    // =============================================================================================================
    //  Banner (Registration Control)
    // =============================================================================================================

    /**
     * 'maintenance' (blocking everyone but Platform Admins), 'registration' (one or more kinds of sign-up are closed) or 'hidden'.
     * The waitlist numbers count only people still WAITING, and only for organizations and counselors: individuals have no waitlist.
     *
     * @return array<string, mixed>
     */
    public function banner(string $timezone): array
    {
        if ($this->settings->maintenanceOn()) {
            return [
                'state' => 'maintenance',
                'reopen_at' => $this->settings->maintenanceReopenAt()?->setTimezone($timezone),
                'url' => route('platform.registration'),
            ];
        }

        $labels = config('platform-settings.registration_roles');
        $closed = collect($this->settings->registrationStatus())->reject(fn (bool $open) => $open)->keys();

        if ($closed->isEmpty()) {
            return ['state' => 'hidden'];
        }

        $waiting = $this->waitlist->enabled() ? array_intersect_key($this->waitlist->waitingCounts(), array_flip($closed->all())) : [];

        return [
            'state' => 'registration',
            'closed' => $closed->mapWithKeys(fn (string $role) => [$role => $labels[$role]['label'] ?? ucfirst($role)])->all(),
            'all_closed' => $closed->count() === count($labels),
            'waiting' => $waiting,
            'url' => route('platform.registration'),
        ];
    }
}
