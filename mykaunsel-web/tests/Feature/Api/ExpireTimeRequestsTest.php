<?php

namespace Tests\Feature\Api;

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use App\Models\User;
use App\Notifications\TimeRequestAnsweredClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `requests:expire`: a time request nobody answered in time is closed and the person is told, once. The clock stands at Monday 2 November 2026,
 * 09:00 Malaysian time (see AvailabilityFixtures).
 */
class ExpireTimeRequestsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function request(User $who, \App\Models\CounselorProfile $aina, \DateTimeInterface $expires, BookingRequestStatus $status = BookingRequestStatus::Pending): BookingRequest
    {
        return BookingRequest::create([
            'user_id' => $who->id, 'counselor_user_id' => $aina->user_id, 'context_org_id' => $this->platform->id, 'requested_date' => '2026-11-05',
            'requested_time' => '10:00:00', 'duration_minutes' => 60, 'session_mode' => 'online', 'status' => $status, 'expires_at' => $expires,
        ]);
    }

    public function test_only_requests_past_their_time_are_closed_and_each_person_is_told_once(): void
    {
        Notification::fake();
        $aina = $this->counselor('Aina', [$this->platform]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $late = $this->request($a, $aina, now()->subMinute());
        $waiting = $this->request($b, $aina, now()->addHour());
        $answered = $this->request($c, $aina, now()->subHour(), BookingRequestStatus::Rejected);

        $this->artisan('requests:expire')->expectsOutput('1 request(s) closed.')->assertSuccessful();

        $this->assertSame(BookingRequestStatus::Expired, $late->fresh()->status);
        $this->assertNotNull($late->fresh()->responded_at);
        $this->assertSame(BookingRequestStatus::Pending, $waiting->fresh()->status);
        $this->assertSame(BookingRequestStatus::Rejected, $answered->fresh()->status);
        Notification::assertSentTo($a, TimeRequestAnsweredClient::class, function (TimeRequestAnsweredClient $n) use ($a) {
            $text = implode("\n", [$n->toMail($a)->subject, ...$n->toMail($a)->introLines]);
            $this->assertStringContainsString('expired', $text);
            $this->assertStringContainsString('Aina', $text);

            return true;
        });
        Notification::assertNotSentTo($b, TimeRequestAnsweredClient::class);
        Notification::assertNotSentTo($c, TimeRequestAnsweredClient::class);

        // Again: nothing is closed twice, nobody is told twice.
        $this->artisan('requests:expire')->expectsOutput('0 request(s) closed.')->assertSuccessful();
        Notification::assertSentToTimes($a, TimeRequestAnsweredClient::class, 1);
    }

    public function test_the_edge_is_to_the_second(): void
    {
        $aina = $this->counselor('Aina', [$this->platform]);
        $request = $this->request(User::factory()->create(), $aina, now()->addMinutes(10));

        Carbon::setTestNow(now()->addMinutes(10)->subSecond());
        $this->artisan('requests:expire')->assertSuccessful();
        $this->assertSame(BookingRequestStatus::Pending, $request->fresh()->status);

        Carbon::setTestNow(now()->addSecond());
        $this->artisan('requests:expire')->assertSuccessful();
        $this->assertSame(BookingRequestStatus::Expired, $request->fresh()->status);
    }

    public function test_a_mail_failure_never_stops_the_closing(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('mail down'));
        $aina = $this->counselor('Aina', [$this->platform]);
        $request = $this->request(User::factory()->create(), $aina, now()->subMinute());

        $this->artisan('requests:expire')->assertSuccessful();

        $this->assertSame(BookingRequestStatus::Expired, $request->fresh()->status);
    }

    public function test_it_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");

        $this->assertStringContainsString('requests:expire', $commands);
    }
}
