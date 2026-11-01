<?php

namespace Tests\Feature\Counselor;

use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\CounselorLanguage;
use App\Models\CounselorProfile;
use App\Models\CounselorSpecialization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The counselor's own Profile page. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class CounselorProfilePageTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(?CounselorProfile $profile = null)
    {
        return $this->actingAs(($profile ?? $this->aina)->user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    private function save(array $over = [], ?CounselorProfile $who = null)
    {
        return $this->as($who)->put(route('counselor.profile.update'), $over + ['buffer_minutes' => 0]);
    }

    public function test_the_page_shows_the_counselors_own_verification_details(): void
    {
        $this->aina->forceFill(['pa_number' => 'PA-12345', 'pa_valid_until' => '2026-11-20'])->save();

        $this->as()->get(route('counselor.profile'))->assertOk()
            ->assertSee($this->aina->kb_number)->assertSee('PA-12345')->assertSee('20 Nov 2026')->assertSee('18 days left')->assertSee('Approved');
    }

    public function test_a_suspended_profile_says_people_can_not_book_and_an_expired_pa_says_expired(): void
    {
        $this->aina->forceFill(['verification_status' => VerificationStatus::Suspended, 'pa_valid_until' => '2026-10-01'])->save();

        $this->as()->get(route('counselor.profile'))->assertOk()->assertSee('Your profile is suspended')->assertSee('Expired');
    }

    public function test_saving_changes_the_names_lists_and_booking_settings(): void
    {
        $this->save([
            'display_name_org' => '  Dr Aina Binti Ali ', 'display_name_independent' => 'Aina Counselling',
            'languages' => ['English', 'Tamil'], 'specializations' => ['Anxiety', 'Grief and loss'],
            'accepts_requests' => 1, 'meeting_provider' => 'Zoom', 'buffer_minutes' => 15,
        ])->assertSessionHas('status', 'Profile saved.');

        $p = $this->aina->fresh();
        $this->assertSame('Dr Aina Binti Ali', $p->display_name_org);
        $this->assertSame('Aina Counselling', $p->display_name_independent);
        $this->assertTrue($p->accepts_requests);
        $this->assertSame('Zoom', $p->meeting_provider);
        $this->assertSame(15, $p->buffer_minutes);
        $this->assertEqualsCanonicalizing(['English', 'Tamil'], $p->languages()->pluck('language')->all());
        $this->assertEqualsCanonicalizing(['Anxiety', 'Grief and loss'], $p->specializations()->pluck('specialization')->all());
    }

    public function test_only_listed_languages_specializations_providers_and_buffers_are_accepted(): void
    {
        $before = $this->aina->languages()->pluck('language')->all();

        $this->save(['languages' => ['Klingon']])->assertSessionHasErrors('languages.0');
        $this->save(['specializations' => ['Anything I like']])->assertSessionHasErrors('specializations.0');
        $this->save(['meeting_provider' => 'Carrier pigeon'])->assertSessionHasErrors('meeting_provider');
        $this->save(['buffer_minutes' => 7])->assertSessionHasErrors('buffer_minutes');
        $this->assertEqualsCanonicalizing($before, $this->aina->languages()->pluck('language')->all());
    }

    public function test_something_the_counselor_already_has_that_is_not_listed_is_kept(): void
    {
        CounselorSpecialization::create(['counselor_profile_id' => $this->aina->id, 'specialization' => 'Play therapy']);
        CounselorLanguage::create(['counselor_profile_id' => $this->aina->id, 'language' => 'Iban']);

        $this->as()->get(route('counselor.profile'))->assertOk()->assertSee('Play therapy')->assertSee('Iban');

        $this->save(['specializations' => ['Play therapy', 'Anxiety'], 'languages' => ['Iban']])->assertSessionHas('status', 'Profile saved.');
        $this->assertEqualsCanonicalizing(['Play therapy', 'Anxiety'], $this->aina->specializations()->pluck('specialization')->all());
    }

    public function test_an_empty_name_goes_back_to_the_account_name_and_nothing_changed_says_so(): void
    {
        $this->aina->forceFill(['display_name_org' => 'Old name'])->save();

        $this->save(['display_name_org' => ''])->assertSessionHas('status', 'Profile saved.');
        $this->assertNull($this->aina->fresh()->display_name_org);

        $this->save(['display_name_org' => ''])->assertSessionHas('status', 'Nothing changed.');
    }

    public function test_the_audit_log_records_which_fields_changed_but_not_the_values(): void
    {
        $this->save(['display_name_org' => 'Secret Display Name', 'buffer_minutes' => 30]);

        $entry = AuditLog::where('action', 'counselor.profile_updated')->sole();
        $this->assertStringContainsString('display_name_org', json_encode($entry->toArray()));
        $this->assertStringNotContainsString('Secret Display Name', json_encode($entry->toArray()));
    }

    public function test_only_a_counselor_with_a_profile_opens_it_and_the_verification_fields_can_not_be_posted(): void
    {
        $kb = $this->aina->kb_number;

        $this->save(['kb_number' => 'HACK', 'verification_status' => 'approved', 'pa_valid_until' => '2099-01-01']);
        $this->assertSame($kb, $this->aina->fresh()->kb_number);
        $this->assertNotSame('2099-01-01', (string) $this->aina->fresh()->pa_valid_until?->format('Y-m-d'));

        $this->actingAs(User::factory()->create())->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'employee'])
            ->get(route('counselor.profile'))->assertRedirect();
    }
}
