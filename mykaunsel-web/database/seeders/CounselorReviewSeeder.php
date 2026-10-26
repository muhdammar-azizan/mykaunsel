<?php

namespace Database\Seeders;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\ComplaintStatus;
use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\SessionMode;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\Complaint;
use App\Models\CounselorDocument;
use App\Models\CounselorKbAttempt;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sample counselor applications and accounts for the Platform Admin → Counselors pages. All made up:
 *   pending (full LKM match / one flagged document / resubmitted / LKM record changed after submitting),
 *   active with the PA about to expire, suspended by the system (PA expired) and by a platform admin, a rejected
 *   application, and an account that others tried to register again with the same KB number.
 * The organization counselors come from CounselorSeeder. Documents are tiny made-up files on the private disk.
 * Safe to run twice.
 */
class CounselorReviewSeeder extends Seeder
{
    private User $admin;

    private Organization $platform;

    public function run(): void
    {
        if (CounselorProfile::where('kb_number', 'KB11023')->exists()) {
            return;
        }

        $this->platform = Organization::where('org_type', 'platform')->firstOrFail();
        $this->admin = User::whereHas('memberships', fn ($q) => $q->where('role', MembershipRole::PlatformAdmin->value))->first()
            ?? User::factory()->create(['name' => 'Platform Administrator']);

        // 1. Pending, everything matches LKM.
        $this->counselor('KB11023', VerificationStatus::Pending, submitted: now()->subDays(2), documents: ['cert', 'pa', 'ic']);

        // 2. Pending, one flagged document and a duplicate-KB attempt against it.
        $haziq = $this->counselor('KB11256', VerificationStatus::Pending, submitted: now()->subDays(6), documents: ['cert', 'pa', 'ic'], flagged: ['ic' => 'The photo is blurry — please upload a clearer scan.']);
        $this->kbAttempt($haziq, 'Muhammad Haziq Ismail', '203.0.113.71', now()->subDays(1));

        // 3. Pending, resubmitted after a rejection.
        $lee = $this->counselor('KB11489', VerificationStatus::Pending, submitted: now()->subDays(3), documents: ['cert', 'pa', 'ic'], extra: [
            'submission_count' => 2,
            'rejection_reason' => 'The PA certificate was cut off at the edge. Please upload the whole page.',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now()->subDays(9),
        ]);
        $this->audit('counselor.rejected', $lee, ['reason' => $lee->rejection_reason, 'changes' => ['status' => ['old' => 'pending', 'new' => 'rejected']]], now()->subDays(9));

        // 4. Pending, the LKM record CHANGED after the application (the PA date is now different — try "re-run").
        $nur = $this->counselor('KB11702', VerificationStatus::Pending, submitted: now()->subDays(9), documents: ['cert', 'pa', 'ic']);
        LkmDirectorySnapshot::where('kb_number', 'KB11702')->update(['pa_valid_until' => now()->addDays(10)->toDateString()]);

        // 5. Active independent whose PA runs out soon.
        $this->counselor('KB11935', VerificationStatus::Approved, documents: ['cert', 'pa'], pa: now()->addDays(12), extra: ['reviewed_by' => $this->admin->id, 'reviewed_at' => now()->subMonths(5)]);

        // 6. Suspended by the system: the PA expired (the scheduled check itself is not built yet — this is what it will produce).
        $ridzuan = $this->counselor('KB13090', VerificationStatus::Suspended, documents: ['cert', 'pa'], pa: Carbon::parse('2025-11-30'), extra: [
            'suspension_reason' => 'Practising certificate expired on 30 Nov 2025.',
            'suspended_at' => Carbon::parse('2025-12-01 00:05:00'),
            'suspension_source' => 'system',
        ]);
        $this->audit('counselor.pa_expired_suspended', $ridzuan, ['reason' => 'Practising certificate expired on 30 Nov 2025.', 'changes' => ['status' => ['old' => 'approved', 'new' => 'suspended']]], Carbon::parse('2025-12-01 00:05:00'), system: true);

        // 7. Suspended by a platform admin.
        $wong = $this->counselor('KB12168', VerificationStatus::Suspended, documents: ['cert', 'pa'], extra: [
            'suspension_reason' => 'Two clients reported unprofessional conduct; suspended while the complaints are reviewed.',
            'suspended_at' => now()->subDays(11),
            'suspended_by' => $this->admin->id,
            'suspension_source' => 'complaint',
        ]);
        $this->audit('counselor.suspended', $wong, ['reason' => $wong->suspension_reason, 'changes' => ['status' => ['old' => 'approved', 'new' => 'suspended']]], now()->subDays(11));

        // 8. Rejected application.
        $farah = $this->counselor('KB12391', VerificationStatus::Rejected, submitted: now()->subDays(14), documents: ['cert', 'pa', 'ic'], flagged: ['cert' => 'The name on the certificate does not match.'], extra: [
            'rejection_reason' => 'The KB certificate does not match the name on the LKM record. Please upload the correct certificate.',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now()->subDays(12),
        ]);
        $this->audit('counselor.rejected', $farah, ['reason' => $farah->rejection_reason, 'changes' => ['status' => ['old' => 'pending', 'new' => 'rejected']]], now()->subDays(12));

        // 9. The established independent counselor others have tried to register again, with sessions and complaints.
        $tan = CounselorProfile::where('kb_number', 'KB10891')->first();
        if ($tan) {
            foreach ([['Tan W. M.', '198.51.100.20', 20], ['Tan Wei Ming', '198.51.100.20', 3], ['Wei Ming Tan', '203.0.113.9', 1]] as [$typed, $ip, $daysAgo]) {
                $this->kbAttempt($tan, $typed, $ip, now()->subDays($daysAgo));
            }
            $this->activity($tan);
        }
    }

    // ---- builders --------------------------------------------------------------------------

    /**
     * @param  list<string>  $documents  types to upload ('cert', 'pa', 'ic')
     * @param  array<string, string>  $flagged  type => note ("needs replacement")
     */
    private function counselor(string $kb, VerificationStatus $status, ?Carbon $submitted = null, array $documents = [], array $flagged = [], ?Carbon $pa = null, array $extra = []): CounselorProfile
    {
        $record = LkmDirectorySnapshot::where('kb_number', $kb)->firstOrFail();

        if ($pa) { // keep the made-up LKM record in step with the date we want to show
            $record->update(['pa_valid_until' => $pa->toDateString()]);
        }

        $user = User::create([
            'name' => $record->full_name,
            'email' => Str::slug($record->full_name).'@example.test',
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),
        ]);

        $profile = CounselorProfile::create($extra + [
            'user_id' => $user->id,
            'kb_number' => $kb,
            'pa_number' => $record->pa_number,
            'pa_valid_until' => $pa?->toDateString() ?? ($status === VerificationStatus::Approved || $status === VerificationStatus::Pending ? '2028-01-20' : $record->pa_valid_until->toDateString()),
            'verification_type' => VerificationType::PlatformVerified,
            'verification_status' => $status,
            'submitted_at' => $submitted,
            'created_at' => ($submitted ?? now()->subMonths(6))->copy()->subHours(2),
            'accepts_requests' => $status === VerificationStatus::Approved,
        ]);
        // Pending profiles should show the date they applied with, not the "2028" placeholder above.
        if ($status === VerificationStatus::Pending && $pa === null) {
            $profile->update(['pa_valid_until' => $record->pa_valid_until->toDateString()]);
        }

        Membership::create([
            'user_id' => $user->id, 'org_id' => $this->platform->id, 'role' => MembershipRole::Counselor,
            'status' => MembershipStatus::Active, 'join_source' => JoinSource::Manual, 'joined_at' => $profile->created_at,
        ]);

        foreach ($documents as $type) {
            $isPdf = $type !== 'ic';
            $path = Str::random(40).($isPdf ? '.pdf' : '.png');
            Storage::disk(config('counselors.documents_disk'))->put($path, $isPdf ? self::pdf(CounselorDocument::TYPES[$type]) : self::png());

            $profile->documents()->create([
                'type' => $type,
                'path' => $path,
                'original_name' => ['cert' => 'kb-certificate.pdf', 'pa' => 'practising-certificate.pdf', 'ic' => 'identity-card.png'][$type],
                'size_bytes' => strlen($isPdf ? self::pdf('x') : self::png()),
                'status' => isset($flagged[$type]) ? CounselorDocument::STATUS_NEEDS_REPLACEMENT : CounselorDocument::STATUS_UNREVIEWED,
                'note' => $flagged[$type] ?? null,
                'uploaded_at' => $submitted ?? $profile->created_at,
            ]);
        }

        $profile->languages()->create(['language' => 'Bahasa Malaysia']);
        $profile->specializations()->create(['specialization' => 'Stres & Kebimbangan']);

        return $profile->fresh();
    }

    private function kbAttempt(CounselorProfile $profile, string $name, string $ip, Carbon $at): void
    {
        $attempt = new CounselorKbAttempt([
            'counselor_profile_id' => $profile->id, 'kb_number' => $profile->kb_number, 'submitted_name' => $name, 'ip' => $ip,
        ]);
        $attempt->created_at = $at;
        $attempt->save();

        Audit::log('counselor.kb_duplicate_flagged', $profile, ['attempt_id' => $attempt->id, 'seeded' => true], null, [
            'actor_type' => 'guest', 'at' => $at, 'ip' => $ip, 'user_agent' => null, 'request_id' => (string) Str::uuid(),
        ]);
    }

    private function audit(string $action, CounselorProfile $profile, array $metadata, Carbon $at, bool $system = false): void
    {
        Audit::log($action, $profile, $metadata + ['seeded' => true], $system ? 'system' : $this->admin, [
            'at' => $at, 'ip' => $system ? null : '203.0.113.5', 'user_agent' => null, 'request_id' => (string) Str::uuid(),
        ]);
    }

    /** Sessions and complaints for one counselor — counts only are ever shown for these. */
    private function activity(CounselorProfile $tan): void
    {
        $client = User::create(['name' => 'Klien Contoh (made up)', 'email' => 'klien.contoh@example.test', 'password' => Hash::make(Str::random(32)), 'email_verified_at' => now()]);
        Membership::create(['user_id' => $client->id, 'org_id' => $this->platform->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $make = function (int $daysFromNow, BookingStatus $status) use ($tan, $client) {
            $entry = CalendarEntry::create([
                'counselor_user_id' => $tan->user_id, 'entry_type' => CalendarEntryType::AvailableSlot, 'context_org_id' => $this->platform->id,
                'entry_date' => now()->addDays($daysFromNow)->toDateString(), 'start_time' => '14:00:00', 'duration_minutes' => 60,
                'session_mode' => SessionMode::Online, 'is_available' => false,
            ]);

            return Booking::create([
                'org_id' => $this->platform->id, 'user_id' => $client->id, 'counselor_user_id' => $tan->user_id, 'calendar_entry_id' => $entry->id,
                'booking_mode' => BookingMode::SlotBased, 'status' => $status, 'session_mode' => SessionMode::Online,
            ]);
        };

        $make(-30, BookingStatus::Completed);
        $make(-14, BookingStatus::Completed);
        $past = $make(-7, BookingStatus::Completed);
        $make(-3, BookingStatus::Cancelled);
        $make(2, BookingStatus::Confirmed);
        $make(9, BookingStatus::Confirmed);

        Complaint::create(['reporter_user_id' => $client->id, 'counselor_user_id' => $tan->user_id, 'booking_id' => $past->id, 'description' => 'Sample complaint (made up).', 'status' => ComplaintStatus::Open]);
        Complaint::create(['reporter_user_id' => $client->id, 'counselor_user_id' => $tan->user_id, 'description' => 'Older sample complaint (made up).', 'status' => ComplaintStatus::Resolved]);
    }

    public static function pdf(string $title): string
    {
        $text = 'SAMPLE - '.$title.' (made-up document)';
        $stream = "BT /F1 14 Tf 20 100 Td ({$text}) Tj ET";

        return "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 400 200]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            .'4 0 obj<</Length '.strlen($stream).">>stream\n{$stream}\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }

    /** A 1×1 PNG. */
    public static function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }
}
