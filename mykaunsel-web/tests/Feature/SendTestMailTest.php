<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** mail:test: one plain e-mail, and plain warnings when the settings could not work from a phone. */
class SendTestMailTest extends TestCase
{
    public function test_it_sends_one_e_mail_and_warns_about_the_log_mailer_and_a_local_address(): void
    {
        config(['mail.default' => 'array', 'app.url' => 'http://localhost', 'mail.from.address' => 'hello@example.com']);

        $this->artisan('mail:test someone@example.test')
            ->expectsOutputToContain('APP_URL points at this computer')
            ->expectsOutputToContain('MAIL_FROM_ADDRESS is an example address')
            ->assertSuccessful();

        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
    }

    public function test_a_real_address_and_a_reachable_url_get_no_warnings_and_a_failure_is_reported_without_a_password(): void
    {
        config(['mail.default' => 'array', 'app.url' => 'https://test.example.org', 'mail.from.address' => 'me@gmail.test']);

        $this->artisan('mail:test someone@example.test')->doesntExpectOutputToContain('points at this computer')->assertSuccessful();

        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('Connection refused'));
        $this->artisan('mail:test someone@example.test')->expectsOutputToContain('Sending failed')->assertFailed();
    }
}
