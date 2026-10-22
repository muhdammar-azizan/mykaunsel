<?php

namespace Tests\Feature\Counselor;

use App\Enums\VerificationType;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Services\LkmVerificationService;
use App\Support\PaValidity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** "Valid until <date>" means valid for the WHOLE of that day — one rule, used everywhere. */
class PaValidityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, config('app.timezone')));
    }

    public function test_the_boundary_yesterday_today_tomorrow(): void
    {
        $this->at('2026-10-22 00:00:00');
        $this->assertFalse(PaValidity::isValid(Carbon::parse('2026-10-21')), 'yesterday: expired');
        $this->assertTrue(PaValidity::isValid(Carbon::parse('2026-10-22')), 'today: still valid');
        $this->assertTrue(PaValidity::isValid(Carbon::parse('2026-10-23')), 'tomorrow: valid');
    }

    public function test_today_stays_valid_right_up_to_the_last_second_and_expires_at_midnight(): void
    {
        $date = Carbon::parse('2026-10-22');

        $this->at('2026-10-22 00:00:01');
        $this->assertTrue(PaValidity::isValid($date));

        $this->at('2026-10-22 23:59:59');
        $this->assertTrue(PaValidity::isValid($date), 'the old rule (midnight) would have called this expired all day');

        $this->at('2026-10-23 00:00:00');
        $this->assertFalse(PaValidity::isValid($date));
        $this->assertTrue(PaValidity::isExpired($date));
    }

    public function test_it_uses_the_application_timezone_not_the_dates_own(): void
    {
        config(['app.timezone' => 'Asia/Kuala_Lumpur']);
        date_default_timezone_set('Asia/Kuala_Lumpur');
        Carbon::setTestNow(Carbon::parse('2026-10-22 23:30:00', 'Asia/Kuala_Lumpur')); // still the 22nd in KL, already the 23rd in... nowhere earlier

        $this->assertTrue(PaValidity::isValid(Carbon::parse('2026-10-22', 'UTC')));
        $this->assertSame('2026-10-22 23:59:59', PaValidity::lastMoment(Carbon::parse('2026-10-22', 'UTC'))->format('Y-m-d H:i:s'));

        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
    }

    public function test_unknown_dates_are_never_valid(): void
    {
        $this->assertFalse(PaValidity::isValid(null));
        $this->assertTrue(PaValidity::isExpired(null));
        $this->assertNull(PaValidity::daysLeft(null));
        $this->assertFalse(PaValidity::expiresWithin(null, 30));
    }

    public function test_days_left_counts_whole_calendar_days(): void
    {
        $this->at('2026-10-22 18:00:00');

        $this->assertSame(0, PaValidity::daysLeft(Carbon::parse('2026-10-22')), 'valid until today = 0 days left');
        $this->assertSame(1, PaValidity::daysLeft(Carbon::parse('2026-10-23')));
        $this->assertSame(30, PaValidity::daysLeft(Carbon::parse('2026-11-21')));
        $this->assertSame(-1, PaValidity::daysLeft(Carbon::parse('2026-10-21')));
    }

    public function test_expiring_soon_means_valid_but_within_the_window(): void
    {
        $this->at('2026-10-22 09:00:00');

        $this->assertTrue(PaValidity::expiresWithin(Carbon::parse('2026-10-22'), 30), 'last day counts');
        $this->assertTrue(PaValidity::expiresWithin(Carbon::parse('2026-11-21'), 30), 'exactly 30 days');
        $this->assertFalse(PaValidity::expiresWithin(Carbon::parse('2026-11-22'), 30), '31 days');
        $this->assertFalse(PaValidity::expiresWithin(Carbon::parse('2026-10-21'), 30), 'already expired is not "expiring"');
    }

    public function test_the_counselor_profile_follows_the_same_rule(): void
    {
        $this->at('2026-10-22 15:00:00');
        $profile = fn (string $date) => new CounselorProfile(['pa_valid_until' => $date, 'verification_type' => VerificationType::PlatformVerified]);

        $this->assertFalse($profile('2026-10-21')->isPaValid());
        $this->assertTrue($profile('2026-10-21')->isExpired());
        $this->assertTrue($profile('2026-10-22')->isPaValid(), 'valid until today, checked in the afternoon');
        $this->assertFalse($profile('2026-10-22')->isExpired());
        $this->assertTrue($profile('2026-10-23')->isPaValid());
        $this->assertFalse((new CounselorProfile)->isPaValid(), 'no date at all');
    }

    public function test_the_lkm_check_follows_the_same_rule(): void
    {
        $this->at('2026-10-22 15:00:00');
        $service = app(LkmVerificationService::class);

        foreach (['2026-10-21' => false, '2026-10-22' => true, '2026-10-23' => true] as $validUntil => $expected) {
            $kb = 'KB'.str_pad((string) (90000 + (int) substr($validUntil, -2)), 5, '0', STR_PAD_LEFT);
            LkmDirectorySnapshot::create([
                'kb_number' => $kb, 'pa_number' => 'PA12345', 'full_name' => 'Aminah Ahmad', 'status' => 'Aktif',
                'pa_valid_from' => '2025-01-01', 'pa_valid_until' => $validUntil,
            ]);

            $this->assertSame($expected, $service->isPaValid($kb), "PA valid until {$validUntil}");
            $this->assertSame($expected, $service->verify($kb, 'PA12345', 'Aminah Ahmad'), "verify() with PA valid until {$validUntil}");
            $this->assertSame($expected ? null : 'Your practicing certificate (PA) has expired.', $service->verificationFailureReason('KB'.str_pad((string) (90000 + (int) substr($validUntil, -2)), 5, '0', STR_PAD_LEFT), 'PA12345', 'Aminah Ahmad'));
        }
    }
}
