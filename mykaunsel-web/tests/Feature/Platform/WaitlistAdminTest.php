<?php

namespace Tests\Feature\Platform;

use App\Jobs\SendWaitlistEmail;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\PlatformSetting;
use App\Models\WaitlistEntry;
use App\Models\WaitlistSuppression;
use App\Notifications\WaitlistSignUpReopened;
use App\Services\PlatformSettings;
use App\Services\WaitlistHasher;
use App\Services\WaitlistService;
use Database\Seeders\WaitlistSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * The Platform Admin side of the waitlist (docs/WAITLIST.md): the Waitlist section of Registration Control, "Notify all",
 * removing one address, the export, and the retention rule. Organization and Counselor only; individuals have no waitlist.
 */
class WaitlistAdminTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function hasher(): WaitlistHasher
    {
        return app(WaitlistHasher::class);
    }

    private function add(string $type, string $email, string $status = 'waiting', int $signedUpDaysAgo = 0, ?int $notifiedDaysAgo = null): WaitlistEntry
    {
        $notified = $status === 'notified' ? now()->subDays($notifiedDaysAgo ?? 1) : null;

        $entry = new WaitlistEntry([
            'type' => $type, 'email' => $email, 'email_hash' => $this->hasher()->emailHash($type, $email), 'status' => $status,
            'notified_at' => $notified, 'emailed_at' => $notified,
        ]);
        $entry->created_at = now()->subDays($signedUpDaysAgo);
        $entry->updated_at = now()->subDays($signedUpDaysAgo);
        $entry->save();

        return $entry;
    }

    private function close(string ...$types): void
    {
        foreach ($types as $type) {
            $this->as()->post(route('platform.registration.toggle', $type), ['state' => 'closed'])->assertSessionHas('status');
        }
    }

    private function notify(string $type = 'organization')
    {
        return $this->as()->post(route('platform.registration.waitlist.notify', $type));
    }

    private function page(array $query = [])
    {
        return $this->as()->get(route('platform.registration', $query));
    }

    private function log(string $action)
    {
        return AuditLog::where('action', $action)->orderBy('id')->get();
    }

    /** A "Notify all" button that carries the disabled attribute (the CSS class disabled:... is on every button and does not count). */
    private function disabledNotifyButton(): string
    {
        return '/<button[^>]*\sdisabled(?=[\s>=])[^>]*>\s*Notify all/s';
    }

    private function onDemandSent(string $email): bool
    {
        try {
            Notification::assertSentOnDemand(WaitlistSignUpReopened::class, fn ($n, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === $email);

            return true;
        } catch (\PHPUnit\Framework\AssertionFailedError) {
            return false;
        }
    }

    // ---- access ------------------------------------------------------------------------------------------------

    public function test_only_platform_admins_can_use_the_waitlist_actions(): void
    {
        $entry = $this->add('organization', 'ana@example.com');
        $orgAdmin = Membership::where('role', 'org_admin')->firstOrFail();
        $session = ['current_org_id' => $orgAdmin->org_id, 'current_role' => 'org_admin'];
        Notification::fake();

        foreach ([
            fn ($t) => $t->post(route('platform.registration.waitlist.notify', 'organization')),
            fn ($t) => $t->delete(route('platform.registration.waitlist.remove', $entry)),
            fn ($t) => $t->get(route('platform.registration.waitlist.export', 'organization')),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin->user)->withSession($session))->assertForbidden();
        }

        $this->flushSession();
        auth()->logout();
        $this->post(route('platform.registration.waitlist.notify', 'organization'))->assertRedirect();
        $this->get(route('platform.registration.waitlist.export', 'organization'))->assertRedirect();

        $this->assertSame(WaitlistEntry::WAITING, $entry->fresh()->status);
        $this->assertSame(0, AuditLog::where('category', 'waitlist')->count());
        Notification::assertNothingSent();
    }

    public function test_individuals_have_no_waitlist_actions(): void
    {
        $this->as()->post('/platform/registration/waitlist/individual/notify')->assertNotFound();
        $this->as()->get('/platform/registration/waitlist/individual/export')->assertNotFound();
    }

    // ---- the section on the Registration Control page ------------------------------------------------------------

    public function test_the_section_has_a_tab_for_organizations_and_counselors_only(): void
    {
        $html = $this->page()->assertOk()->assertSee('Waitlist')->assertSee('Waitlist by kind of sign-up')->getContent();

        $this->assertStringContainsString('waitlist=organization', $html);
        $this->assertStringContainsString('waitlist=counselor', $html);
        $this->assertStringNotContainsString('waitlist=individual', $html);
        $this->assertSame(2, substr_count($html, 'waiting on the waitlist'), 'a count for organizations and counselors, none for individuals');
    }

    public function test_the_counts_cover_waiting_notified_and_opted_out_and_only_the_waiting_ones_go_on_the_cards(): void
    {
        $this->add('organization', 'a@example.com');
        $this->add('organization', 'b@example.com');
        $this->add('organization', 'c@example.com', 'notified');
        $this->add('counselor', 'd@example.com');
        WaitlistSuppression::create(['type' => 'organization', 'email_hash' => str_repeat('a', 64), 'created_at' => now()]);

        $this->assertSame(['organization' => 2, 'counselor' => 1], app(WaitlistService::class)->waitingCounts());
        $this->assertSame(['waiting' => 2, 'notified' => 1, 'opted_out' => 1], app(WaitlistService::class)->counts()['organization']);

        $this->page()->assertSee('2 waiting on the waitlist')->assertSee('1 waiting on the waitlist')
            ->assertSeeText('2 waiting')->assertSeeText('1 already emailed')->assertSeeText('1 opted out');
    }

    public function test_the_table_lists_address_date_in_the_admins_time_zone_and_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', 'UTC'));
        $this->add('organization', 'wait@example.com');
        $this->add('organization', 'sent@example.com', 'notified', 3, 1);
        $this->add('counselor', 'other@example.com');

        $this->page(['waitlist' => 'organization'])->assertOk()
            ->assertSee('wait@example.com')->assertSee('sent@example.com')->assertDontSee('other@example.com')
            ->assertSee('Waiting')->assertSee('Notified')
            ->assertSee('27 Oct 2026, 6:00 PM')   // 10:00 UTC is 18:00 in Kuala Lumpur
            ->assertSee('26 Oct 2026, 6:00 PM');

        $this->page(['waitlist' => 'counselor'])->assertSee('other@example.com')->assertDontSee('wait@example.com');
    }

    public function test_search_pages_and_empty_states(): void
    {
        foreach (range(1, 30) as $i) {
            $this->add('organization', sprintf('person%02d@example.com', $i), signedUpDaysAgo: 40 - $i);
        }

        $first = $this->page(['waitlist' => 'organization'])->assertOk();
        $this->assertSame(25, $first->viewData('waitlist')['entries']->count());
        $this->assertSame(5, $this->page(['waitlist' => 'organization', 'wpage' => 2])->viewData('waitlist')['entries']->count());
        $this->page(['waitlist' => 'organization', 'wpage' => 9])->assertRedirect();

        $this->page(['waitlist' => 'organization', 'q' => 'PERSON07'])->assertSee('person07@example.com')->assertDontSee('person08@example.com');
        $this->page(['waitlist' => 'organization', 'q' => '%'])->assertSee('No address matches your search.');
        $this->page(['waitlist' => 'organization', 'q' => 'zzz'])->assertSee('Clear');

        $this->page(['waitlist' => 'counselor'])->assertSee('Nobody is on this waitlist yet.');
        $this->page(['waitlist' => 'individual'])->assertOk()->assertSee('person30@example.com'); // an unknown kind falls back to the first tab (newest first)
    }

    public function test_without_its_secret_key_the_section_says_so_and_nothing_can_be_sent(): void
    {
        config(['waitlist.hmac_key' => '']);

        $this->page()->assertOk()->assertSee('The waitlist is switched off')->assertSee('WAITLIST_HMAC_KEY');
        $this->assertContains('The waitlist is switched off (no WAITLIST_HMAC_KEY).', app(WaitlistService::class)->notifyBlockers('organization'));
    }

    // ---- "Notify all" is off while it would be wrong --------------------------------------------------------------

    public function test_notify_all_is_off_with_its_reason_while_the_sign_up_is_closed(): void
    {
        $this->add('organization', 'ana@example.com');
        $this->close('organization');

        $response = $this->page(['waitlist' => 'organization'])->assertSee('Sign-up is still closed. Open it first');
        $this->assertMatchesRegularExpression($this->disabledNotifyButton(), $response->getContent());
    }

    public function test_notify_all_is_on_once_the_sign_up_is_open_and_the_window_shows_how_many(): void
    {
        $this->add('organization', 'a@example.com');
        $this->add('organization', 'b@example.com');

        $response = $this->page(['waitlist' => 'organization'])->assertSee('Email 2 people?')->assertSee('Each gets')->assertSee('one')->assertDontSee('Sign-up is still closed');
        $this->assertDoesNotMatchRegularExpression($this->disabledNotifyButton(), $response->getContent());
        $this->assertMatchesRegularExpression('/<button[^>]*>\s*Notify all \(2\)/s', $response->getContent());
    }

    public function test_notify_all_is_refused_by_the_server_when_closed_and_sends_nothing(): void
    {
        Notification::fake();
        $this->add('organization', 'ana@example.com');
        $this->close('organization');

        $this->notify()->assertSessionHas('error', fn ($m) => str_contains($m, 'Nobody was emailed') && str_contains($m, 'still closed'));

        $this->assertSame(WaitlistEntry::WAITING, WaitlistEntry::firstOrFail()->status);
        $this->assertSame(0, $this->log('waitlist.notified')->count());
        Notification::assertNothingSent();
    }

    public function test_notify_all_is_refused_during_maintenance_and_when_nobody_is_waiting(): void
    {
        Notification::fake();
        $this->add('organization', 'ana@example.com');
        PlatformSetting::findOrFail('maintenance')->update(['value' => true]);
        $this->app->forgetInstance(PlatformSettings::class);

        $this->notify()->assertSessionHas('error', fn ($m) => str_contains($m, 'Maintenance mode is on'));

        PlatformSetting::findOrFail('maintenance')->update(['value' => false]);
        $this->app->forgetInstance(PlatformSettings::class);
        WaitlistEntry::query()->delete();
        $this->notify()->assertSessionHas('error', fn ($m) => str_contains($m, 'Nobody is waiting'));

        Notification::assertNothingSent();
        $this->assertSame(0, $this->log('waitlist.notified')->count());
    }

    // ---- notifying -------------------------------------------------------------------------------------------------------

    public function test_notify_all_emails_each_waiting_address_once_marks_it_notified_and_audits_counts_only(): void
    {
        Notification::fake();
        $this->add('organization', 'a@example.com');
        $this->add('organization', 'b@example.com');
        $already = $this->add('organization', 'c@example.com', 'notified', 5, 2);
        $this->add('counselor', 'd@example.com');

        $this->notify()->assertRedirect()->assertSessionHas('status', fn ($m) => str_contains($m, '2 emails queued'));

        $this->assertTrue($this->onDemandSent('a@example.com'));
        $this->assertTrue($this->onDemandSent('b@example.com'));
        $this->assertFalse($this->onDemandSent('c@example.com'), 'already emailed once');
        $this->assertFalse($this->onDemandSent('d@example.com'), 'another kind of sign-up');

        $this->assertSame(['notified', 'notified', 'notified', 'waiting'], WaitlistEntry::orderBy('id')->pluck('status')->all());
        $this->assertNotNull(WaitlistEntry::where('email', 'a@example.com')->first()->notified_at);
        $this->assertTrue($already->fresh()->notified_at->equalTo($already->notified_at), 'an earlier notification is not touched');

        $log = $this->log('waitlist.notified')->sole();
        $this->assertSame([$this->admin->id, 'waitlist', ['type' => 'organization', 'sent' => 2, 'failed' => 0]], [$log->actor_user_id, $log->category, $log->metadata]);
        $this->assertStringNotContainsString('@', json_encode($log->metadata));
    }

    public function test_running_it_again_sends_nothing_more(): void
    {
        Notification::fake();
        $this->add('organization', 'a@example.com');

        $this->notify()->assertSessionHas('status');
        $this->notify()->assertSessionHas('error', fn ($m) => str_contains($m, 'Nobody is waiting'));

        Notification::assertSentOnDemandTimes(WaitlistSignUpReopened::class, 1);
        $this->assertSame(1, $this->log('waitlist.notified')->count());
    }

    public function test_an_address_claimed_by_someone_else_in_the_meantime_is_not_emailed_twice(): void
    {
        Notification::fake();
        $first = $this->add('organization', 'a@example.com');
        $this->add('organization', 'b@example.com');
        $stolen = false;

        // Another admin's run gets to the first address between this run listing it and claiming it.
        \DB::listen(function ($query) use (&$stolen, $first) {
            if (! $stolen && str_contains(self::plainSql($query->sql), 'from waitlist_entries') && str_contains($query->sql, 'order by')) {
                $stolen = true;
                \DB::table('waitlist_entries')->where('id', $first->id)->update(['status' => 'notified', 'notified_at' => now(), 'emailed_at' => now()]);
            }
        });

        $this->notify()->assertSessionHas('status');

        $this->assertTrue($stolen);
        $this->assertFalse($this->onDemandSent('a@example.com'), 'the other run owns it');
        $this->assertTrue($this->onDemandSent('b@example.com'));
        $this->assertSame(['type' => 'organization', 'sent' => 1, 'failed' => 1], $this->log('waitlist.notified')->sole()->metadata);
    }

    public function test_a_claim_can_only_be_won_once(): void
    {
        $entry = $this->add('organization', 'a@example.com');
        $waitlist = app(WaitlistService::class);

        $this->assertNotNull($waitlist->claim($entry->id));
        $this->assertNull($waitlist->claim($entry->id));
        $this->assertNull($waitlist->claim(999999));
    }

    public function test_the_same_job_run_twice_still_sends_one_email(): void
    {
        Notification::fake();
        $entry = $this->add('organization', 'a@example.com');
        $claimedAt = app(WaitlistService::class)->claim($entry->id);
        $job = new SendWaitlistEmail($entry->id, $claimedAt->toIso8601String());

        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        app()->call([new SendWaitlistEmail($entry->id, $claimedAt->toIso8601String()), 'handle']);

        Notification::assertSentOnDemandTimes(WaitlistSignUpReopened::class, 1);
    }

    public function test_the_emails_are_queued_after_the_commit_and_spread_out_sixty_a_minute(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', 'UTC'));
        foreach (range(1, 130) as $i) {
            $this->add('organization', sprintf('p%03d@example.com', $i));
        }

        $this->notify()->assertSessionHas('status');

        $jobs = Queue::pushed(SendWaitlistEmail::class);
        $this->assertCount(130, $jobs);
        $this->assertTrue($jobs->every(fn ($job) => $job->afterCommit === true), 'queued only once the claims are committed');
        $minutes = $jobs->map(fn ($job) => (int) round(now()->diffInSeconds($job->delay, false) / 60))->countBy()->sortKeys()->all();
        $this->assertSame([0 => 60, 1 => 60, 2 => 10], $minutes);
        $this->assertSame(60, config('waitlist.send_per_minute'));
    }

    public function test_the_email_says_it_is_open_links_to_the_normal_sign_up_page_and_can_be_opted_out_in_one_click(): void
    {
        $this->add('organization', 'ana@example.com');
        $this->add('counselor', 'bala@example.com');

        $this->notify()->assertSessionHas('status');
        $this->notify('counselor')->assertSessionHas('status');

        $messages = collect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
        $this->assertCount(2, $messages);

        $org = $messages->first(fn ($m) => $m->getOriginalMessage()->getTo()[0]->getAddress() === 'ana@example.com')->getOriginalMessage();
        $body = $org->getTextBody().$org->getHtmlBody();

        $this->assertSame('MyKaunsel organization sign-up is open again', $org->getSubject());
        $this->assertStringContainsString(route('organizations.signup.create'), $body);
        $this->assertStringNotContainsString(route('counselors.signup.create'), $body);
        $this->assertStringContainsString('This is the only email we will send you about it', $body);
        $this->assertStringNotContainsString('token', strtolower(preg_replace('#https?://\S+#', '', $body)));

        $unsubscribe = $this->hasher()->unsubscribeUrl('organization', 'ana@example.com');
        $this->assertStringContainsString($unsubscribe, $body);
        $this->assertSame('<'.$unsubscribe.'>', $org->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $org->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());

        // The link in the email really works, with the one-click POST a mail program sends.
        $this->flushSession();
        auth()->logout();
        $this->post($unsubscribe, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee('You are opted out');
        $this->assertNull(WaitlistEntry::where('email', 'ana@example.com')->first());
        $this->assertTrue(WaitlistSuppression::where('type', 'organization')->where('email_hash', $this->hasher()->emailHash('organization', 'ana@example.com'))->exists());
    }

    // ---- at the moment of sending ------------------------------------------------------------------------------------

    public function test_someone_who_opts_out_or_is_removed_after_the_claim_is_not_emailed(): void
    {
        Notification::fake();
        $gone = $this->add('organization', 'gone@example.com');
        $removed = $this->add('organization', 'removed@example.com');
        $goneAt = app(WaitlistService::class)->claim($gone->id);
        $removedAt = app(WaitlistService::class)->claim($removed->id);

        app(WaitlistService::class)->optOut('organization', $gone->email_hash);
        $removed->delete();

        app()->call([new SendWaitlistEmail($gone->id, $goneAt->toIso8601String()), 'handle']);
        app()->call([new SendWaitlistEmail($removed->id, $removedAt->toIso8601String()), 'handle']);

        Notification::assertNothingSent();
    }

    public function test_an_email_saying_open_is_never_sent_while_the_sign_up_is_closed_again(): void
    {
        Notification::fake();
        $entry = $this->add('organization', 'ana@example.com');
        $claimedAt = app(WaitlistService::class)->claim($entry->id);
        $this->close('organization');

        app()->call([new SendWaitlistEmail($entry->id, $claimedAt->toIso8601String()), 'handle']);

        Notification::assertNothingSent();
        $this->assertSame([WaitlistEntry::WAITING, null], [$entry->fresh()->status, $entry->fresh()->notified_at], 'back on the list, to be emailed after the next reopening');
    }

    public function test_an_old_claim_does_not_send_for_a_newer_one(): void
    {
        Notification::fake();
        $entry = $this->add('organization', 'ana@example.com');
        $old = app(WaitlistService::class)->claim($entry->id);
        app(WaitlistService::class)->release($entry->id, $old);
        Carbon::setTestNow(now()->addHour());
        $new = app(WaitlistService::class)->claim($entry->id);

        app()->call([new SendWaitlistEmail($entry->id, $old->toIso8601String()), 'handle']);
        Notification::assertNothingSent();

        app()->call([new SendWaitlistEmail($entry->id, $new->toIso8601String()), 'handle']);
        Notification::assertSentOnDemandTimes(WaitlistSignUpReopened::class, 1);
    }

    public function test_an_email_that_cannot_be_sent_leaves_the_address_waiting_to_be_tried_again(): void
    {
        $entry = $this->add('organization', 'ana@example.com');
        $claimedAt = app(WaitlistService::class)->claim($entry->id);
        $job = new SendWaitlistEmail($entry->id, $claimedAt->toIso8601String());

        $this->app->instance(Dispatcher::class, new class implements Dispatcher
        {
            public function send($notifiables, $notification): void
            {
                throw new RuntimeException('mail server down');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null): void
            {
                throw new RuntimeException('mail server down');
            }
        });

        try {
            app()->call([$job, 'handle']);
            $this->fail('the failure should reach the queue so it retries');
        } catch (RuntimeException) {
        }

        $this->assertNull($entry->fresh()->emailed_at, 'the mark that stops a second email is taken off again');
        $this->assertSame(WaitlistEntry::NOTIFIED, $entry->fresh()->status, 'still claimed while the queue retries');

        // When the queue gives up, the address goes back to waiting.
        $job->failed(new RuntimeException('gave up'));
        $this->assertSame([WaitlistEntry::WAITING, null], [$entry->fresh()->status, $entry->fresh()->notified_at]);
    }

    // ---- removing and exporting ----------------------------------------------------------------------------------------

    public function test_removing_an_address_deletes_it_and_audits_the_record_id_only(): void
    {
        $entry = $this->add('organization', 'ana@example.com');
        $this->add('organization', 'keep@example.com');

        $this->as()->delete(route('platform.registration.waitlist.remove', $entry))->assertRedirect()->assertSessionHas('status');

        $this->assertNull(WaitlistEntry::where('email', 'ana@example.com')->first());
        $this->assertNotNull(WaitlistEntry::where('email', 'keep@example.com')->first());
        $this->assertSame(0, WaitlistSuppression::count(), 'removing is not opting out');

        $log = $this->log('waitlist.entry_removed')->sole();
        $this->assertSame([$this->admin->id, ['entry_id' => $entry->id, 'type' => 'organization']], [$log->actor_user_id, $log->metadata]);
        $this->assertStringNotContainsString('@', json_encode($log->metadata));
        $this->as()->delete(route('platform.registration.waitlist.remove', $entry))->assertNotFound();
    }

    public function test_the_export_follows_the_tab_has_no_formulas_and_is_audited_with_a_count(): void
    {
        $this->add('organization', 'a@example.com');
        $this->add('organization', 'b@example.com', 'notified', 4, 2);
        $this->add('counselor', 'other@example.com');
        WaitlistEntry::query()->insert(['type' => 'organization', 'email' => '=HYPERLINK("http://evil.example")@x.test', 'email_hash' => str_repeat('c', 64), 'status' => 'waiting', 'created_at' => now(), 'updated_at' => now()]);
        WaitlistSuppression::create(['type' => 'organization', 'email_hash' => str_repeat('d', 64), 'created_at' => now()]);

        $response = $this->as()->get(route('platform.registration.waitlist.export', 'organization'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFEmail,Type,Status,\"Signed up (UTC)\",\"Notified (UTC)\"\n", $body);
        $this->assertStringContainsString('a@example.com,organization,waiting,', $body);
        $this->assertStringContainsString('b@example.com,organization,notified,', $body);
        $this->assertStringNotContainsString('other@example.com', $body);
        $this->assertStringNotContainsString(str_repeat('d', 64), $body);
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil.example\"\")@x.test\"", $body, 'formulas are made safe');
        $this->assertCount(4, array_filter(explode("\n", trim(substr($body, 3)))));

        $log = $this->log('waitlist.exported')->sole();
        $this->assertSame([$this->admin->id, ['type' => 'organization', 'count' => 3]], [$log->actor_user_id, $log->metadata]);
    }

    public function test_the_export_is_rate_limited_and_refuses_a_list_that_is_too_big(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->as()->get(route('platform.registration.waitlist.export', 'counselor'))->assertOk();
        }
        $this->as()->get(route('platform.registration.waitlist.export', 'counselor'))->assertStatus(429);

        Carbon::setTestNow(now()->addMinutes(2));
        config(['waitlist.export_max_rows' => 1]);
        $this->add('organization', 'a@example.com');
        $this->add('organization', 'b@example.com');
        $before = $this->log('waitlist.exported')->count();

        $this->as()->get(route('platform.registration.waitlist.export', 'organization'))->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'more than the 1'));
        $this->assertSame($before, $this->log('waitlist.exported')->count());
    }

    // ---- retention ---------------------------------------------------------------------------------------------------

    public function test_retention_removes_notified_after_30_days_and_never_notified_after_a_year_and_keeps_the_opt_outs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', 'UTC'));
        $this->add('organization', 'notified-31@example.com', 'notified', 60, 31);
        $this->add('organization', 'notified-29@example.com', 'notified', 60, 29);
        $this->add('counselor', 'waiting-366@example.com', 'waiting', 366);
        $this->add('counselor', 'waiting-364@example.com', 'waiting', 364);
        WaitlistSuppression::create(['type' => 'organization', 'email_hash' => str_repeat('e', 64), 'created_at' => now()->subYears(2)]);

        $this->artisan('waitlist:purge', ['--dry-run' => true])->expectsOutputToContain('Would remove 1 notified and 1 never-notified addresses')->assertSuccessful();
        $this->assertSame(4, WaitlistEntry::count());
        $this->assertSame(0, $this->log('waitlist.purged')->count());

        $this->artisan('waitlist:purge')->expectsOutputToContain('Removed 1 notified and 1 never-notified addresses')->assertSuccessful();

        $this->assertEqualsCanonicalizing(['notified-29@example.com', 'waiting-364@example.com'], WaitlistEntry::pluck('email')->all());
        $this->assertSame(1, WaitlistSuppression::count(), 'the opt-out hashes stay: a deliberate choice');

        $log = $this->log('waitlist.purged')->sole();
        $this->assertSame(['system', null, ['notified' => 1, 'waiting' => 1]], [$log->actor_type, $log->actor_user_id, $log->metadata]);

        // Nothing more to do the next day: no empty audit entry.
        $this->artisan('waitlist:purge')->expectsOutputToContain('Removed 0 notified and 0 never-notified')->assertSuccessful();
        $this->assertSame(1, $this->log('waitlist.purged')->count());
    }

    public function test_the_retention_periods_are_configurable_and_the_task_runs_daily(): void
    {
        config(['waitlist.retention.notified_days' => 5]);
        $this->add('organization', 'a@example.com', 'notified', 20, 6);

        $this->artisan('waitlist:purge')->assertSuccessful();
        $this->assertSame(0, WaitlistEntry::count());

        Artisan::call('schedule:list');
        $this->assertMatchesRegularExpression('/0\s+3\s+\*\s+\*\s+\*\s+php artisan waitlist:purge/', Artisan::output());
    }

    // ---- never an address in the audit log -------------------------------------------------------------------------------

    public function test_no_waitlist_audit_entry_ever_holds_an_email_address(): void
    {
        Notification::fake();
        $entry = $this->add('organization', 'ana@example.com');
        $this->add('organization', 'bala@example.com', 'notified', 90, 60);
        $this->add('counselor', 'chong@example.com');

        $this->notify();
        $this->as()->get(route('platform.registration.waitlist.export', 'organization'))->assertOk()->streamedContent();
        $this->as()->delete(route('platform.registration.waitlist.remove', $entry));
        $this->artisan('waitlist:purge')->assertSuccessful();

        $logs = AuditLog::where('category', 'waitlist')->get();
        $this->assertEqualsCanonicalizing(['waitlist.notified', 'waitlist.exported', 'waitlist.entry_removed', 'waitlist.purged'], $logs->pluck('action')->all());
        foreach ($logs as $log) {
            $this->assertStringNotContainsString('@', json_encode($log->only(['metadata', 'target_name', 'actor_name'])), $log->action);
            $this->assertStringNotContainsString('example.com', json_encode($log->getAttributes()), $log->action);
        }

        $this->as()->get(route('platform.audit-log', ['category' => 'waitlist']))->assertOk()->assertSee('Waitlist');
    }

    // ---- sample data -----------------------------------------------------------------------------------------------------------

    public function test_the_sample_data_has_no_individuals_and_opt_outs_are_hashes(): void
    {
        $this->seed(WaitlistSeeder::class);

        $this->assertSame(['counselor', 'organization'], WaitlistEntry::query()->distinct()->orderBy('type')->pluck('type')->all());
        $this->assertSame(11, WaitlistEntry::count());
        $this->assertSame(4, WaitlistSuppression::count());
        $this->assertStringNotContainsString('@', json_encode(WaitlistSuppression::all()));
        $this->assertSame(4, WaitlistEntry::where('status', 'notified')->count());

        $this->seed(WaitlistSeeder::class);
        $this->assertSame(11, WaitlistEntry::count(), 'safe to run twice');
    }
}
