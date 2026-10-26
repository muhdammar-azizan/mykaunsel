<?php

namespace Database\Seeders;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\ComplaintStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\SessionMode;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\Complaint;
use App\Models\ComplaintReviewNote;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sample complaints for Platform Admin → Complaints. Everything is made up (every text starts with "[Sample]").
 * Covers every status, old and new complaints, with and without a session, an organization counselor, a very long
 * text, and text with HTML characters (shown as plain text). Safe to run twice.
 */
class ComplaintReviewSeeder extends Seeder
{
    private User $admin;

    public function run(): void
    {
        if (Complaint::where('description', 'like', '[Sample]%')->exists()) {
            return;
        }

        $this->admin = User::whereHas('memberships', fn ($q) => $q->where('role', MembershipRole::PlatformAdmin->value))->first()
            ?? User::factory()->create(['name' => 'Platform Administrator']);

        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->first();
        $klinik = Organization::where('name', 'Klinik Kaunseling Damai')->first();
        $ahmad = CounselorProfile::where('kb_number', 'KB10234')->first();
        $siti = CounselorProfile::where('kb_number', 'KB10567')->first();
        $aina = CounselorProfile::where('kb_number', 'KB14245')->first();
        $tan = CounselorProfile::where('kb_number', 'KB10891')->first();

        if (! $ahmad || ! $umpsa) {
            return; // the organization counselors come from CounselorSeeder
        }

        $student = $this->person('Pelajar Contoh (made up)', 'pelajar.aduan@example.test', $umpsa, MembershipRole::Student);
        $client = $klinik ? $this->person('Klien Klinik (made up)', 'klien.aduan@example.test', $klinik, MembershipRole::Student) : null;

        // 1. Open for 9 days (red), about an organization counselor, through their organization.
        $this->complaint($student, $ahmad, 'Open', 9, $this->booking($student, $ahmad, $umpsa, -10), '[Sample] The session started 20 minutes late and the counselor did not apologise.');

        // 2. Open for 6 days (amber), with an HTML-looking text that must show as plain text.
        $this->complaint($student, $siti, 'Open', 6, $this->booking($student, $siti, $umpsa, -7), '[Sample] <b>Bold?</b> <script>alert("x")</script> The counselor was dismissive. Line one.'."\n".'Line two of the same complaint.');

        // 3. Open for 1 day, about a clinic counselor.
        if ($aina && $client && $klinik) {
            $this->complaint($client, $aina, 'Open', 1, $this->booking($client, $aina, $klinik, -2), '[Sample] The clinic charged a fee that was not agreed beforehand.');
        }

        // 4. Under review, with an internal note and a status change in its history.
        if ($tan) {
            $review = $this->complaint($student, $tan, 'UnderReview', 12, null, '[Sample] Not linked to any session: the counselor answered a message in a rude tone.');
            $this->audit('complaint.status_changed', $review, ['changes' => ['status' => ['old' => 'open', 'new' => 'under_review']]], 11);
            $note = ComplaintReviewNote::create(['complaint_id' => $review->id, 'user_id' => $this->admin->id, 'body' => 'Asked the counselor for their side. Waiting for a reply.']);
            $this->audit('complaint.note_added', $review, ['note_id' => $note->id], 10);
        }

        // 5. Resolved.
        $this->complaint($student, $ahmad, 'Resolved', 30, $this->booking($student, $ahmad, $umpsa, -32), '[Sample] The meeting link did not work and the session was lost.', closedDaysAgo: 24);

        // 6. Dismissed.
        $this->complaint($student, $siti, 'Dismissed', 40, null, '[Sample] I did not like the advice I was given.', closedDaysAgo: 35);

        // 7. A very long text, to check the layout.
        $this->complaint($student, $ahmad, 'Open', 3, null, '[Sample] '.str_repeat('This is a very long complaint written to test how the page copes with a lot of text. ', 25));
    }

    // ---- builders --------------------------------------------------------------------------

    private function person(string $name, string $email, Organization $org, MembershipRole $role): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make(Str::random(32)), 'email_verified_at' => now()]);
        Membership::firstOrCreate(['user_id' => $user->id, 'org_id' => $org->id], ['role' => $role, 'status' => MembershipStatus::Active, 'joined_at' => now()->subMonths(3)]);

        return $user;
    }

    private function booking(User $client, CounselorProfile $counselor, Organization $org, int $daysFromNow): Booking
    {
        $entry = CalendarEntry::create([
            'counselor_user_id' => $counselor->user_id, 'entry_type' => CalendarEntryType::AvailableSlot, 'context_org_id' => $org->id,
            'entry_date' => now()->addDays($daysFromNow)->toDateString(), 'start_time' => '11:00:00', 'duration_minutes' => 60,
            'session_mode' => SessionMode::Online, 'is_available' => false,
        ]);

        return Booking::create([
            'org_id' => $org->id, 'user_id' => $client->id, 'counselor_user_id' => $counselor->user_id, 'calendar_entry_id' => $entry->id,
            'booking_mode' => BookingMode::SlotBased, 'status' => BookingStatus::Completed, 'session_mode' => SessionMode::Online,
        ]);
    }

    private function complaint(User $reporter, CounselorProfile $counselor, string $status, int $daysAgo, ?Booking $booking, string $text, ?int $closedDaysAgo = null): Complaint
    {
        $enum = ['Open' => ComplaintStatus::Open, 'UnderReview' => ComplaintStatus::UnderReview, 'Resolved' => ComplaintStatus::Resolved, 'Dismissed' => ComplaintStatus::Dismissed][$status];

        $complaint = Complaint::create([
            'reporter_user_id' => $reporter->id, 'counselor_user_id' => $counselor->user_id, 'booking_id' => $booking?->id,
            'description' => $text, 'status' => $enum,
        ]);
        $complaint->forceFill(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($closedDaysAgo ?? $daysAgo)])->saveQuietly();

        if ($closedDaysAgo !== null) {
            $this->audit('complaint.status_changed', $complaint, ['reason' => 'Sample outcome (made up).', 'changes' => ['status' => ['old' => 'under_review', 'new' => $enum->value]]], $closedDaysAgo);
        }

        return $complaint;
    }

    private function audit(string $action, Complaint $complaint, array $metadata, int $daysAgo): void
    {
        Audit::log($action, $complaint, $metadata + ['seeded' => true], $this->admin, [
            'at' => Carbon::now()->subDays($daysAgo), 'ip' => '203.0.113.5', 'user_agent' => null, 'request_id' => (string) Str::uuid(),
        ]);
    }
}
