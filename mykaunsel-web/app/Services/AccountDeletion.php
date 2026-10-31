<?php

namespace App\Services;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Exceptions\AccountNotDeletable;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccountDeleted;
use App\Notifications\BookingCancelledCounselor;
use App\Notifications\MemberLeft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A client deletes their own account (docs/ACCOUNT_DELETION.md; the owner's decisions are in docs/APP_SCREENS.md).
 *
 * The account is NOT removed from the database: bookings, session notes and complaints point at it. What identifies the person is erased and
 * the row stays as an anonymous stub (`deleted_at` set, a name of "Deleted user", an address nobody can use, a password nobody knows).
 *
 *  - Erased: name, e-mail, profile photo, two-factor secrets, every sign-in (phones and web), AI chat conversations, Feed posts and comments
 *    (the words are blanked), likes, muted people, in-app notices, and the keys that make a retried booking safe.
 *  - Ended: every later booking (the time is given back and each counselor is told), time requests nobody answered, and the membership of an
 *    organization (its admins are e-mailed the NAME only, as when someone leaves).
 *  - Kept: booking records (without a name; the 90 day rule is a scheduled clean-up), what counselors and organizations wrote about a session,
 *    the person's reports of a counselor (the reporter stays on record, owner decision), reports of Feed writing, consent history.
 *
 * Counselors and administrators are refused: other people depend on their records. All of it happens in one transaction with the same lock
 * order as booking (the person, the counselors' profiles, the bookings). E-mails go out after, and a failing e-mail never undoes anything.
 */
class AccountDeletion
{
    public function __construct(private readonly MembershipService $memberships, private readonly AvailabilityService $availability) {}

    /**
     * @return array{cancelled: int}
     *
     * @throws AccountNotDeletable
     */
    public function delete(User $user): array
    {
        $oldEmail = $user->email;
        $name = $user->name;
        $organizations = [];
        $cancelled = [];
        $avatar = null;

        DB::transaction(function () use ($user, &$organizations, &$cancelled, &$avatar) {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->deleted_at !== null) {
                throw AccountNotDeletable::alreadyDeleted();
            }

            if ($this->isStaff($locked)) {
                throw AccountNotDeletable::staff();
            }

            $organizations = $this->endMemberships($locked);
            $cancelled = $this->cancelLaterSessions($locked);
            $this->closeRequests($locked);
            $this->erase($locked);
            $avatar = $locked->avatar_path;
            $this->anonymise($locked);

            Audit::log('account.deleted', $locked, [
                'cancelled_booking_ids' => array_map(fn (Booking $b) => $b->getKey(), $cancelled),
                'org_ids' => array_map(fn (Organization $o) => $o->getKey(), $organizations),
            ], $locked);
        });

        $this->afterwards($oldEmail, $name, $organizations, $cancelled, $avatar);

        return ['cancelled' => count($cancelled)];
    }

    /** Whether the password and the typed word are right is the caller's job; this only stops staff and repeats. */
    public function isStaff(User $user): bool
    {
        return CounselorProfile::query()->where('user_id', $user->getKey())->exists()
            || Membership::query()->where('user_id', $user->getKey())
                ->whereIn('role', [MembershipRole::Counselor->value, MembershipRole::OrgAdmin->value, MembershipRole::PlatformAdmin->value])->exists();
    }

    /** @return list<Organization> the organizations they belonged to, for the e-mail to their admins */
    private function endMemberships(User $user): array
    {
        $rows = Membership::query()->where('user_id', $user->getKey())
            ->whereIn('role', array_map(fn ($r) => $r->value, MembershipService::CLIENT_ROLES))
            ->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::PendingApproval->value, MembershipStatus::NoticePeriod->value, MembershipStatus::Suspended->value])
            ->with('organization')->lockForUpdate()->get();

        // Someone who only WAITED for approval leaves quietly, as when they withdraw: their organization is not told.
        $told = $rows->filter(fn (Membership $m) => $m->status !== MembershipStatus::PendingApproval)->map->organization->values()->all();

        foreach ($rows as $row) {
            $row->update(['status' => MembershipStatus::Offboarded, 'offboarded_at' => now(), 'offboard_reason' => 'Account deleted by the person']);
        }

        return $told;
    }

    /**
     * Every later confirmed session, whatever the organization (unlike leaving an organization there is no 7 day grace: the person is gone).
     *
     * @return list<Booking>
     */
    private function cancelLaterSessions(User $user): array
    {
        $now = $this->availability->now();

        $bookings = Booking::query()->where('user_id', $user->getKey())->where('status', BookingStatus::Confirmed->value)->whereNotNull('calendar_entry_id')
            ->with(['calendarEntry', 'counselor'])->orderBy('id')->get();

        CounselorProfile::query()->whereIn('user_id', $bookings->pluck('counselor_user_id')->unique()->sort()->values())->orderBy('user_id')->lockForUpdate()->get();

        $cancelled = [];

        foreach ($bookings as $booking) {
            $booking = Booking::query()->whereKey($booking->getKey())->with(['calendarEntry', 'counselor'])->lockForUpdate()->firstOrFail();
            $entry = $booking->calendarEntry;

            if ($booking->status !== BookingStatus::Confirmed || $entry === null || ! $this->availability->startOf($entry)->gt($now)) {
                continue; // under way or over: part of the record
            }

            $booking->update(['status' => BookingStatus::Cancelled]);
            CalendarEntry::query()->whereKey($entry->getKey())->update(['is_available' => true]); // B9: in the same transaction
            $cancelled[] = $booking;
        }

        return $cancelled;
    }

    private function closeRequests(User $user): void
    {
        BookingRequest::query()->where('user_id', $user->getKey())->where('status', BookingRequestStatus::Pending->value)
            ->update(['status' => BookingRequestStatus::Withdrawn->value, 'responded_at' => now()]);
    }

    /** What the person wrote or collected, and every way back in. */
    private function erase(User $user): void
    {
        $id = $user->getKey();

        // AI chats (messages first).
        $sessions = DB::table('chat_sessions')->where('user_id', $id)->pluck('id');
        DB::table('messages')->whereIn('chat_session_id', $sessions)->delete();
        DB::table('chat_sessions')->where('user_id', $id)->delete();

        // Feed: the words go, the rows stay (other people's replies, likes and reports point at them).
        foreach (['feed_posts', 'feed_comments'] as $table) {
            DB::table($table)->where('user_id', $id)->update(['content' => '']);
            DB::table($table)->where('user_id', $id)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        }
        DB::table('post_reactions')->where('user_id', $id)->delete();
        DB::table('feed_mutes')->where('user_id', $id)->orWhere('muted_user_id', $id)->delete();

        DB::table('user_notifications')->where('recipient_user_id', $id)->delete();
        DB::table('booking_idempotency_keys')->where('user_id', $id)->delete();
        DB::table('org_allowed_members')->where('claimed_by_user_id', $id)->update(['claimed_by_user_id' => null]);

        // Every way in: phones, and the web.
        DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $id)->delete();
        DB::table('sessions')->where('user_id', $id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }

    private function anonymise(User $user): void
    {
        $user->forceFill([
            'name' => 'Deleted user',
            'email' => 'deleted-'.$user->getKey().'-'.Str::lower(Str::random(8)).'@deleted.invalid',
            'email_verified_at' => null,
            'pending_email' => null,
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'avatar_path' => null,
            'timezone' => 'Asia/Kuala_Lumpur',
            'notification_preferences' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
            'ai_consent_at' => null,
            'deleted_at' => now(),
        ])->save();
    }

    /**
     * @param  list<Organization>  $organizations
     * @param  list<Booking>  $cancelled
     */
    private function afterwards(string $oldEmail, string $name, array $organizations, array $cancelled, ?string $avatar): void
    {
        $this->tryTo(fn () => $avatar ? Storage::disk(config('account.avatar_disk'))->delete($avatar) : null);
        $this->tryTo(fn () => Notification::route('mail', $oldEmail)->notify(new AccountDeleted(count($cancelled))));

        foreach ($cancelled as $booking) {
            $this->tryTo(function () use ($booking) {
                $entry = $booking->calendarEntry;
                $start = $this->availability->startOf($entry);

                $booking->counselor->notify(new BookingCancelledCounselor([
                    'client' => 'A client',
                    'date' => $start->format('l, j F Y'),
                    'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $entry->duration_minutes)->format('g:i A'),
                    'minutes' => (int) $entry->duration_minutes,
                    'mode' => $booking->session_mode->value,
                ]));
            });
        }

        // The organization hears the NAME of someone who goes, as when a member leaves, and nothing about sessions.
        foreach ($organizations as $organization) {
            foreach ($this->memberships->admins($organization) as $admin) {
                $this->tryTo(fn () => $admin->notify(new MemberLeft($organization->name, $name)));
            }
        }
    }

    private function tryTo(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
