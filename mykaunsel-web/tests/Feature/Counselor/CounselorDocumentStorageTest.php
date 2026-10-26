<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CounselorDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(VerificationStatus $status = VerificationStatus::Pending): User
    {
        $platform = Organization::firstOrCreate(['name' => 'MyKaunsel Platform'], ['org_type' => 'platform', 'access_model' => 'open', 'subscription_status' => 'active']);
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'org_id' => $platform->id, 'role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        CounselorProfile::create([
            'user_id' => $user->id, 'kb_number' => 'KB10001', 'pa_number' => 'PA10001', 'pa_valid_until' => now()->addYear(),
            'verification_type' => VerificationType::PlatformVerified, 'verification_status' => $status,
        ]);

        return $user;
    }

    private function upload(User $user, array $files)
    {
        return $this->actingAs($user)->post(route('counselors.signup.documents.store'), $files);
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

        $this->upload($user, [
            'cert' => UploadedFile::fake()->create('my-kb-certificate.pdf', 120, 'application/pdf'),
            'pa' => UploadedFile::fake()->create('pa.jpg', 80, 'image/jpeg'),
            'ic' => UploadedFile::fake()->create('ic-front.png', 90, 'image/png'),
        ])->assertRedirect(route('counselors.signup.waiting-approval'));

        $documents = $user->counselorProfile->documents()->get()->keyBy('type');
        $this->assertSame(['cert', 'ic', 'pa'], $documents->keys()->sort()->values()->all());

        foreach ($documents as $document) {
            Storage::disk('counselor_documents')->assertExists($document->path);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.(pdf|jpg|jpeg|png)$/', $document->path, 'random name');
            $this->assertStringNotContainsString('certificate', $document->path);
            $this->assertSame(CounselorDocument::STATUS_UNREVIEWED, $document->status);
            $this->assertNotNull($document->uploaded_at);
        }

        // The applicant's own file name is kept as a label only.
        $this->assertSame('my-kb-certificate.pdf', $documents['cert']->original_name);
        $this->assertGreaterThan(0, $documents['cert']->size_bytes);

        $this->assertSame([], Storage::disk('public')->allFiles(), 'nothing was written to the public disk');
    }

    public function test_replacing_a_document_removes_the_old_file_and_clears_its_mark(): void
    {
        Storage::fake('counselor_documents');
        $user = $this->applicant();
        $disk = Storage::disk('counselor_documents');

        $this->upload($user, ['ic' => UploadedFile::fake()->create('ic1.pdf', 50, 'application/pdf')]);
        $document = $user->counselorProfile->documents()->where('type', 'ic')->first();
        $first = $document->path;
        $document->update(['status' => CounselorDocument::STATUS_NEEDS_REPLACEMENT, 'note' => 'Blurry']);

        $this->upload($user, ['ic' => UploadedFile::fake()->create('ic2.pdf', 50, 'application/pdf')]);
        $document->refresh();

        $this->assertNotSame($first, $document->path);
        $disk->assertMissing($first);
        $disk->assertExists($document->path);
        $this->assertSame(CounselorDocument::STATUS_UNREVIEWED, $document->status, 'a new upload starts un-reviewed');
        $this->assertNull($document->note);
        $this->assertSame(1, $user->counselorProfile->documents()->where('type', 'ic')->count(), 'still one row per document type');
    }

    public function test_the_application_counts_as_submitted_once_all_three_documents_are_in(): void
    {
        Storage::fake('counselor_documents');
        $user = $this->applicant();
        $file = fn (string $n) => UploadedFile::fake()->create($n, 50, 'application/pdf');

        $this->upload($user, ['cert' => $file('a.pdf'), 'pa' => $file('b.pdf')]);
        $this->assertNull($user->counselorProfile->fresh()->submitted_at, 'two of three is not a submission yet');

        $this->upload($user, ['ic' => $file('c.pdf')]);
        $this->assertNotNull($user->counselorProfile->fresh()->submitted_at);
    }

    public function test_a_rejected_applicant_who_uploads_again_goes_back_to_pending(): void
    {
        Storage::fake('counselor_documents');
        $user = $this->applicant(VerificationStatus::Rejected);
        $user->counselorProfile->update(['rejection_reason' => 'Documents unclear']);

        $this->upload($user, ['cert' => UploadedFile::fake()->create('c.pdf', 50, 'application/pdf')]);

        $profile = $user->counselorProfile->fresh();
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        $this->assertNull($profile->rejection_reason);
    }

    // ---- the one-off moves -----------------------------------------------------------------

    /** The old path columns no longer exist; the moving migration was written for them, so put them back for the test. */
    private function withLegacyColumns(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            foreach (['cert_document_path', 'pa_document_path', 'ic_document_path'] as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    private function legacyPaths(User $user, array $paths): void
    {
        DB::table('counselor_profiles')->where('user_id', $user->id)->update($paths);
    }

    private function moveMigration(): object
    {
        return require database_path('migrations/2026_10_23_000001_move_counselor_documents_to_private_disk.php');
    }

    public function test_existing_public_documents_are_moved_renamed_and_the_public_copy_removed(): void
    {
        $this->withLegacyColumns();
        Storage::fake('counselor_documents');
        Storage::fake('public');
        Storage::disk('public')->put('counselor-documents/AAA.pdf', 'kb-bytes');
        Storage::disk('public')->put('counselor-documents/BBB.png', 'pa-bytes');
        Storage::disk('public')->put('counselor-documents/CCC.jpg', 'ic-bytes');
        Storage::disk('public')->put('avatars/me.jpg', 'avatar'); // other public files must be left alone
        Storage::disk('public')->put('location-photos/clinic.jpg', 'photo');

        $user = $this->applicant();
        $this->legacyPaths($user, [
            'cert_document_path' => 'counselor-documents/AAA.pdf',
            'pa_document_path' => 'counselor-documents/BBB.png',
            'ic_document_path' => 'counselor-documents/CCC.jpg',
        ]);

        $result = $this->moveMigration()->moveDocuments();

        $this->assertSame(['moved' => 3, 'missing' => 0, 'orphans' => 0], $result);

        $row = DB::table('counselor_profiles')->where('user_id', $user->id)->first();
        $this->assertSame('kb-bytes', Storage::disk('counselor_documents')->get($row->cert_document_path));
        $this->assertSame('pa-bytes', Storage::disk('counselor_documents')->get($row->pa_document_path));
        $this->assertSame('ic-bytes', Storage::disk('counselor_documents')->get($row->ic_document_path));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.pdf$/', $row->cert_document_path);
        $this->assertStringEndsWith('.png', $row->pa_document_path);

        Storage::disk('public')->assertMissing('counselor-documents/AAA.pdf');
        $this->assertFalse(Storage::disk('public')->exists('counselor-documents'), 'the public directory is gone');
        Storage::disk('public')->assertExists('avatars/me.jpg');
        Storage::disk('public')->assertExists('location-photos/clinic.jpg');
    }

    public function test_unreferenced_files_left_in_the_public_folder_are_moved_out_of_reach_too(): void
    {
        $this->withLegacyColumns();
        Storage::fake('counselor_documents');
        Storage::fake('public');
        Storage::disk('public')->put('counselor-documents/forgotten.pdf', 'old upload nobody points to');

        $result = $this->moveMigration()->moveDocuments();

        $this->assertSame(1, $result['orphans']);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $orphans = Storage::disk('counselor_documents')->files('orphaned');
        $this->assertCount(1, $orphans);
        $this->assertSame('old upload nobody points to', Storage::disk('counselor_documents')->get($orphans[0]));
    }

    public function test_the_move_is_safe_to_repeat_and_reports_missing_files(): void
    {
        $this->withLegacyColumns();
        Storage::fake('counselor_documents');
        Storage::fake('public');
        Storage::disk('public')->put('counselor-documents/AAA.pdf', 'kb-bytes');
        $user = $this->applicant();
        $this->legacyPaths($user, ['cert_document_path' => 'counselor-documents/AAA.pdf', 'pa_document_path' => 'counselor-documents/lost.pdf']);
        $column = fn (string $name) => DB::table('counselor_profiles')->where('user_id', $user->id)->value($name);

        $this->assertSame(['moved' => 1, 'missing' => 1, 'orphans' => 0], $this->moveMigration()->moveDocuments());
        $after = $column('cert_document_path');

        $this->assertSame(['moved' => 0, 'missing' => 1, 'orphans' => 0], $this->moveMigration()->moveDocuments(), 'nothing is moved twice');
        $this->assertSame($after, $column('cert_document_path'));
        $this->assertSame('counselor-documents/lost.pdf', $column('pa_document_path'), 'a reference to a missing file is left untouched');
        $this->assertCount(1, Storage::disk('counselor_documents')->allFiles());
    }

    public function test_the_three_path_columns_are_copied_into_the_documents_table(): void
    {
        $this->withLegacyColumns();
        $user = $this->applicant();
        $this->legacyPaths($user, ['cert_document_path' => 'aaaa.pdf', 'ic_document_path' => 'cccc.png']);

        $copy = require database_path('migrations/2026_10_24_000002_create_counselor_documents_table.php');
        $this->assertSame(2, $copy->copyLegacyPaths());

        $documents = CounselorProfile::where('user_id', $user->id)->first()->documents->keyBy('type');
        $this->assertSame(['cert', 'ic'], $documents->keys()->sort()->values()->all());
        $this->assertSame('aaaa.pdf', $documents['cert']->path);
        $this->assertSame(CounselorDocument::STATUS_UNREVIEWED, $documents['cert']->status);
        $this->assertNotNull($documents['cert']->uploaded_at);
    }

    public function test_the_old_path_columns_are_gone_so_there_is_one_source_of_truth(): void
    {
        $this->assertFalse(Schema::hasColumn('counselor_profiles', 'cert_document_path'));
        $this->assertFalse(Schema::hasColumn('counselor_profiles', 'pa_document_path'));
        $this->assertFalse(Schema::hasColumn('counselor_profiles', 'ic_document_path'));
        $this->assertTrue(Schema::hasTable('counselor_documents'));
    }

    public function test_the_public_storage_link_setting_is_untouched(): void
    {
        $this->assertSame([public_path('storage') => storage_path('app/public')], config('filesystems.links'));
    }
}
