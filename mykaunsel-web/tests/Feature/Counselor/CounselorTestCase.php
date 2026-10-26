<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\CounselorReviewSeeder;
use Database\Seeders\CounselorSeeder;
use Database\Seeders\LkmSnapshotSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Shared set-up for the Counselors page tests: the real sample data (so the tests also prove the seeders work),
 * a platform admin, and the private document disk faked so no test writes real files.
 */
abstract class CounselorTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected array $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('counselor_documents');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Platform, organizations, LKM records, the organization counselors and the review samples (see CounselorReviewSeeder). */
    protected function seedSamples(): void
    {
        $this->seed([PlatformSeeder::class, OrganizationSeeder::class, LkmSnapshotSeeder::class, CounselorSeeder::class, CounselorReviewSeeder::class]);
        $this->signInAdmin();
    }

    protected function signInAdmin(): void
    {
        $this->admin = User::where('email', 'admin@mykaunsel.com')->firstOrFail();
        $this->admin->update(['timezone' => 'Asia/Kuala_Lumpur']);
        $this->session = ['current_org_id' => Organization::where('org_type', 'platform')->value('id'), 'current_role' => 'platform_admin'];
    }

    protected function as(?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->withSession($this->session);
    }

    protected function profile(string $kb): CounselorProfile
    {
        return CounselorProfile::where('kb_number', $kb)->firstOrFail();
    }

    protected function listPage(array $query = [])
    {
        return $this->as()->get(route('platform.counselors', $query));
    }

    /** @return list<string> the KB numbers shown, in order */
    protected function kbs($response): array
    {
        return $response->viewData('profiles')->getCollection()->pluck('kb_number')->all();
    }
}
