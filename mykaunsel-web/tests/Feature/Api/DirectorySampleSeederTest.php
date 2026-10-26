<?php

namespace Tests\Feature\Api;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\CalendarEntry;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DirectorySampleSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Counselor\CounselorTestCase;

/** The real sample data (the same the developer runs by hand) through the real directory rules. */
class DirectorySampleSeederTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        $this->seed(DirectorySampleSeeder::class);
    }

    private function stranger(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_a_stranger_finds_the_independent_and_open_clinic_counselors_but_not_the_universitys(): void
    {
        $this->stranger();

        $names = collect($this->getJson('/api/v1/counselors')->assertOk()->json('data'))->pluck('name')->all();

        // Tan (independent), Aina (open clinic) and Rajesh (another approved independent from the review samples).
        $this->assertEqualsCanonicalizing(['Kaunselor Tan Wei Ming', 'Kaunselor Aina Syazwani', 'Rajesh a/l Kumar'], $names);
        $this->assertNotContains('Kaunselor Ahmad Zulkarnain', $names);
    }

    public function test_a_student_of_the_closed_university_also_finds_its_counselors(): void
    {
        $user = $this->stranger();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        Membership::create(['user_id' => $user->id, 'org_id' => $umpsa->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $names = collect($this->getJson('/api/v1/counselors')->json('data'))->pluck('name')->all();

        $this->assertContains('Kaunselor Ahmad Zulkarnain', $names);
        $this->assertContains('Kaunselor Siti Nurhaliza', $names);
        $this->assertCount(5, $names);
    }

    public function test_the_cards_have_content_for_every_filter(): void
    {
        $this->stranger();

        $options = $this->getJson('/api/v1/counselors/filters')->assertOk()->json('data');

        $this->assertContains('Trauma', $options['specializations']);
        $this->assertContains('Mandarin', $options['languages']);
        $this->assertSame(['online', 'physical'], $options['session_modes']);
        $this->assertSame(['Pahang'], $options['states']);

        $tan = collect($this->getJson('/api/v1/counselors')->json('data'))->firstWhere('name', 'Kaunselor Tan Wei Ming');
        $this->assertNotNull($tan['next_available']);
        $this->assertSame('independent', $tan['contexts'][0]['type']);
    }

    public function test_running_it_twice_adds_nothing(): void
    {
        $slots = CalendarEntry::count();

        $this->seed(DirectorySampleSeeder::class);

        $this->assertSame($slots, CalendarEntry::count());
    }

    public function test_the_real_sample_counselors_secrets_never_appear(): void
    {
        $this->stranger();

        $text = $this->getJson('/api/v1/counselors')->getContent();

        foreach (['KB10891', 'KB14245', 'tan.weiming@gmail.com', 'aina.syazwani@klinikdamai.com', 'Jalan Contoh', '25000'] as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }
    }
}
