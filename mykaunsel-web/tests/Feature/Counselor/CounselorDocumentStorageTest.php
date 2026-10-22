<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CounselorDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(VerificationStatus $status = VerificationStatus::Pending, array $paths = []): User
    {
        $platform = Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => 'platform', 'access_model' => 'open', 'subscription_status' => 'active']);
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'org_id' => $platform->id, 'role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        CounselorProfile::create([
            'user_id' => $user->id, 'kb_number' => 'KB10001', 'pa_number' => 'PA10001', 'pa_valid_until' => now()->addYear(),
            'verification_type' => VerificationType::PlatformVerified, 'verification_status' => $status,
        ] + $paths);

        return $user;
    }

    public function test_the_disk_is_private_and_separate_from_the_public_one(): void
    {
        $disk = config('filesystems.disks.counselor_documents');

        $this->assertSame('counselor_documents', config('counselors.documents_disk'));
        $this->assertSame('private', $disk['visibility']);
        $this->assertArrayNotHasKey('url', $disk, 'a disk with no url cannot hand out public links');
        $this->assertTrue($disk['throw'], 'failures to save must not be silent');
        $this->assertStringNotContainsString(
            str_replace('\\', '/', storage_path('app/public')),
            str_replace('\\', '/', $disk['root']),
        );
        $this->assertNotContains($disk['root'], array_values(config('filesystems.links')), 'not behind any symlink');
    }

    public function test_uploads_go_to_the_private_disk_with_random_names_and_nothing_is_public(): void
    {
        Storage::fake('counselor_documents');
        Storage::fake('public');
        $user = $this->applicant();

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), [
            'cert' => UploadedFile::fake()->create('my-kb-certificate.pdf', 120, 'application/pdf'),
            'pa' => UploadedFile::fake()->create('pa.jpg', 80, 'image/jpeg'),
            'ic' => UploadedFile::fake()->create('ic-front.png', 90, 'image/png'),
        ])->assertRedirect(route('counselors.signup.waiting-approval'));

        $profile = $user->counselorProfile->fresh();

        foreach (['cert_document_path', 'pa_document_path', 'ic_document_path'] as $column) {
            $path = $profile->{$column};
            Storage::disk('counselor_documents')->assertExists($path);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.(pdf|jpg|jpeg|png)$/', $path, 'random name, original name not kept');
            $this->assertStringNotContainsString('my-kb-certificate', $path);
            $this->assertStringNotContainsString('counselor-documents', $path, 'no longer in the public folder layout');
        }

        $this->assertSame([], Storage::disk('public')->allFiles(), 'nothing was written to the public disk');
    }

    public function test_replacing_a_document_removes_the_old_file(): void
    {
        Storage::fake('counselor_documents');
        $user = $this->applicant();
        $disk = Storage::disk('counselor_documents');

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), ['ic' => UploadedFile::fake()->create('ic1.pdf', 50, 'application/pdf')]);
        $first = $user->counselorProfile->fresh()->ic_document_path;
        $disk->assertExists($first);

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), ['ic' => UploadedFile::fake()->create('ic2.pdf', 50, 'application/pdf')]);
        $second = $user->counselorProfile->fresh()->ic_document_path;

        $this->assertNotSame($first, $second);
        $disk->assertMissing($first);
        $disk->assertExists($second);
    }

    public function test_a_rejected_applicant_who_uploads_again_goes_back_to_pending(): void
    {
        Storage::fake('counselor_documents');
        $user = $this->applicant(VerificationStatus::Rejected);
        $user->counselorProfile->update(['rejection_reason' => 'Documents unclear']);

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), ['cert' => UploadedFile::fake()->create('c.pdf', 50, 'application/pdf')]);

        $profile = $user->counselorProfile->fresh();
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        $this->assertNull($profile->rejection_reason);
    }

    // ---- the one-off move ------------------------------------------------------------------

    private function migration(): object
    {
        return require database_path('migrations/2026_10_23_000001_move_counselor_documents_to_private_disk.php');
    }

    public function test_existing_public_documents_are_moved_renamed_and_the_public_copy_removed(): void
    {
        Storage::fake('counselor_documents');
        Storage::fake('public');
        Storage::disk('public')->put('counselor-documents/AAA.pdf', 'kb-bytes');
        Storage::disk('public')->put('counselor-documents/BBB.png', 'pa-bytes');
        Storage::disk('public')->put('counselor-documents/CCC.jpg', 'ic-bytes');
        Storage::disk('public')->put('avatars/me.jpg', 'avatar'); // other public files must be left alone
        Storage::disk('public')->put('location-photos/clinic.jpg', 'photo');

        $user = $this->applicant(paths: [
            'cert_document_path' => 'counselor-documents/AAA.pdf',
            'pa_document_path' => 'counselor-documents/BBB.png',
            'ic_document_path' => 'counselor-documents/CCC.jpg',
        ]);

        $result = $this->migration()->moveDocuments();

        $this->assertSame(['moved' => 3, 'missing' => 0, 'orphans' => 0], $result);

        $profile = $user->counselorProfile->fresh();
        $this->assertSame('kb-bytes', Storage::disk('counselor_documents')->get($profile->cert_document_path));
        $this->assertSame('pa-bytes', Storage::disk('counselor_documents')->get($profile->pa_document_path));
        $this->assertSame('ic-bytes', Storage::disk('counselor_documents')->get($profile->ic_document_path));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.pdf$/', $profile->cert_document_path);
        $this->assertStringEndsWith('.png', $profile->pa_document_path);

        Storage::disk('public')->assertMissing('counselor-documents/AAA.pdf');
        $this->assertFalse(Storage::disk('public')->exists('counselor-documents'), 'the public directory is gone');
        Storage::disk('public')->assertExists('avatars/me.jpg');
        Storage::disk('public')->assertExists('location-photos/clinic.jpg');
    }

    public function test_unreferenced_files_left_in_the_public_folder_are_moved_out_of_reach_too(): void
    {
        Storage::fake('counselor_documents');
        Storage::fake('public');
        Storage::disk('public')->put('counselor-documents/forgotten.pdf', 'old upload nobody points to');

        $result = $this->migration()->moveDocuments();

        $this->assertSame(1, $result['orphans']);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $orphans = Storage::disk('counselor_documents')->files('orphaned');
        $this->assertCount(1, $orphans);
        $this->assertSame('old upload nobody points to', Storage::disk('counselor_documents')->get($orphans[0]));
    }

    public function test_the_move_is_safe_to_repeat_and_reports_missing_files(): void
    {
        Storage::fake('counselor_documents');
        Storage::fake('public');
        Storage::disk('public')->put('counselor-documents/AAA.pdf', 'kb-bytes');
        $user = $this->applicant(paths: ['cert_document_path' => 'counselor-documents/AAA.pdf', 'pa_document_path' => 'counselor-documents/lost.pdf']);

        $this->assertSame(['moved' => 1, 'missing' => 1, 'orphans' => 0], $this->migration()->moveDocuments());
        $after = $user->counselorProfile->fresh()->cert_document_path;

        $second = $this->migration()->moveDocuments();

        $this->assertSame(['moved' => 0, 'missing' => 1, 'orphans' => 0], $second, 'nothing is moved twice');
        $this->assertSame($after, $user->counselorProfile->fresh()->cert_document_path);
        $this->assertSame('counselor-documents/lost.pdf', $user->counselorProfile->fresh()->pa_document_path, 'a reference to a missing file is left untouched');
        $this->assertSame(1, count(Storage::disk('counselor_documents')->allFiles()));
    }

    public function test_the_public_storage_link_setting_is_untouched(): void
    {
        $this->assertSame([public_path('storage') => storage_path('app/public')], config('filesystems.links'));
    }
}
