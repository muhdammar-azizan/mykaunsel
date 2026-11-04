<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorProfile;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * The Schedule page tells the COUNSELOR why an open time is hidden from clients (a blocked time, or too close to a booked session).
 * Clients never learn it, and it is never shown as "Booked". Clock: Monday 2 November 2026, 09:00 Malaysian time; buffer 15 minutes.
 */
class CounselorHiddenTimesTest extends TestCase
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

    private function as()
    {
        return $this->actingAs($this->aina->user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    public function test_the_reason_is_found_for_a_block_and_for_a_booked_session_and_not_for_a_clear_time(): void
    {
        $this->block($this->aina, '2026-11-11 11:00');                                        // 11:00-12:00
        $nextToBlock = $this->slot($this->aina, $this->platform, '2026-11-11 10:00');         // ends 11:00: inside the buffer of the block

        $booked = $this->slot($this->aina, $this->platform, '2026-11-12 10:00');              // 10:00-11:00
        $this->book($booked, User::factory()->create());
        $nextToSession = $this->slot($this->aina, $this->platform, '2026-11-12 11:00');       // starts when the session ends: inside its buffer

        $clear = $this->slot($this->aina, $this->platform, '2026-11-13 10:00');

        $reasons = app(AvailabilityService::class)->hiddenReasons(collect([$nextToBlock, $nextToSession, $clear]));

        $this->assertSame([
            $nextToBlock->id => AvailabilityService::HIDDEN_BLOCK,
            $nextToSession->id => AvailabilityService::HIDDEN_SESSION,
        ], $reasons);
    }

    public function test_a_block_wins_when_both_reasons_apply(): void
    {
        $session = $this->slot($this->aina, $this->platform, '2026-11-12 09:00');
        $this->book($session, User::factory()->create());
        $this->block($this->aina, '2026-11-12 11:00');
        $between = $this->slot($this->aina, $this->platform, '2026-11-12 10:00');             // touches both buffers

        $this->assertSame([$between->id => AvailabilityService::HIDDEN_BLOCK], app(AvailabilityService::class)->hiddenReasons(collect([$between])));
    }

    public function test_the_schedule_page_shows_the_badge_with_a_reason_and_booked_times_stay_booked(): void
    {
        $this->block($this->aina, '2026-11-11 11:00');
        $this->slot($this->aina, $this->platform, '2026-11-11 10:00');

        $booked = $this->slot($this->aina, $this->platform, '2026-11-12 10:00');
        $this->book($booked, User::factory()->create());
        $this->slot($this->aina, $this->platform, '2026-11-12 11:00');

        $this->slot($this->aina, $this->platform, '2026-11-13 10:00');

        $page = $this->as()->get(route('counselor.planner'))->assertOk();

        $page->assertSee('Hidden from clients: blocked time')->assertSee('Hidden from clients: too close to a session');
        $this->assertSame(1, substr_count($page->getContent(), 'Hidden from clients: blocked time'));
        $this->assertSame(1, substr_count($page->getContent(), 'Hidden from clients: too close to a session'), 'the booked time itself is "Booked", not hidden');
    }

    public function test_a_client_never_sees_the_reason_or_the_hidden_time(): void
    {
        $this->client();   // signed in, like the app
        $this->block($this->aina, '2026-11-11 11:00');
        $hidden = $this->slot($this->aina, $this->platform, '2026-11-11 10:00');
        $this->slot($this->aina, $this->platform, '2026-11-13 10:00');

        $body = $this->availability($this->aina)->assertOk()->getContent();

        $this->assertStringNotContainsString('Hidden', $body);
        $this->assertStringNotContainsString('blocked', $body);
        $this->assertStringNotContainsString($hidden->public_id, $body);
    }
}
