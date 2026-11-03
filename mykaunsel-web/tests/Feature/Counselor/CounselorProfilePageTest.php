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

    // ---- their own specializations: shown on the profile, never a filter -------------------------------------------

    public function test_a_counselor_can_add_a_few_short_specializations_of_their_own_and_keep_the_listed_ones(): void
    {
        $this->save(['specializations' => ['Anxiety'], 'custom_specializations' => ['parenting', '  Play   therapy  ']])->assertSessionHas('status', 'Profile saved.');

        $rows = $this->aina->specializations()->get();
        $this->assertEqualsCanonicalizing(['Parenting', 'Play therapy'], $rows->where('is_custom', true)->pluck('specialization')->all());
        $this->assertSame(['Anxiety'], $rows->where('is_custom', false)->pluck('specialization')->all());

        // The page shows them back, and saving the listed ones again does not remove the counselor's own (or the other way round).
        $this->as()->get(route('counselor.profile'))->assertOk()->assertSee('Parenting')->assertSee('Play therapy');
        $this->save(['specializations' => ['Anxiety', 'Stress'], 'custom_specializations' => ['Parenting', 'Play therapy']]);
        $this->assertCount(4, $this->aina->specializations()->get());
        $this->save(['specializations' => ['Anxiety', 'Stress']]);
        $this->assertSame(['Anxiety', 'Stress'], $this->aina->specializations()->where('is_custom', false)->pluck('specialization')->sort()->values()->all());
        $this->assertSame(0, $this->aina->specializations()->where('is_custom', true)->count());
    }

    public function test_their_own_specializations_are_short_plain_words_and_at_most_three(): void
    {
        $bad = [
            ['one two three four'],                       // more than 3 words
            [str_repeat('a', 31)],                        // more than 30 characters
            ['Call 0123456789'],                          // digits
            ['visit.my-site.com'],                        // a link
            ['a@b'],                                      // an e-mail address
            ['<b>Bold</b>'],                              // markup
            ['Anxiety'],                                  // already in the list
            ['One', 'Two', 'Three', 'Four'],              // more than 3
        ];

        foreach ($bad as $values) {
            $this->save(['custom_specializations' => $values])->assertSessionHasErrors();
        }

        $this->assertSame(0, $this->aina->specializations()->where('is_custom', true)->count());
        $this->save(['custom_specializations' => ['Child & family', 'Self-worth', 'Burnout']])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->aina->specializations()->where('is_custom', true)->count());
    }

    public function test_their_own_specializations_are_not_a_search_filter_and_not_counted_in_the_analytics(): void
    {
        $this->save(['specializations' => ['Anxiety'], 'custom_specializations' => ['Parenting']]);

        $options = app(\App\Services\CounselorDirectory::class)->filterOptions($this->aina->user);
        $this->assertContains('Anxiety', $options['specializations']);
        $this->assertNotContains('Parenting', $options['specializations']);

        $offered = collect((new \ReflectionMethod(\App\Services\AnalyticsService::class, 'specializationsOf'))->invoke(app(\App\Services\AnalyticsService::class), [$this->aina->user_id]));
        $this->assertSame(['anxiety'], array_keys($offered[$this->aina->user_id] ?? []));
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
