<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Privacy Policy, Terms of Use and Help (docs/LEGAL_PAGES.md): public, honest about being drafts, and tied to the numbers the system really uses. */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_pages_are_public(): void
    {
        foreach (['privacy', 'terms', 'help'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_a_draft_says_so_and_marks_what_is_still_to_be_confirmed(): void
    {
        config(['legal.draft' => true, 'api.support_email' => null]);

        foreach (['privacy', 'terms', 'help'] as $name) {
            $this->get(route($name))->assertSee('This is a draft that is still being reviewed', false)->assertSee('To be confirmed');
        }
    }

    public function test_once_reviewed_the_draft_notice_goes_and_a_known_support_address_replaces_the_marker(): void
    {
        config(['legal.draft' => false, 'api.support_email' => 'help@example.test']);

        $this->get(route('help'))->assertDontSee('still being reviewed')->assertSee('help@example.test')->assertDontSee('the support e-mail address');
        $this->get(route('privacy'))->assertSee('mailto:help@example.test', false);
    }

    public function test_the_privacy_policy_quotes_the_retention_the_system_really_uses(): void
    {
        config(['chat.retention_months' => 7, 'account.deleted_booking_days' => 45, 'complaints.retention.closed_years' => 2, 'notifications.retention.read_days' => 11, 'notifications.retention.unread_days' => 22]);

        $this->get(route('privacy'))
            ->assertSee('Conversations are deleted 7 months after your last message.')
            ->assertSee('45 days')
            ->assertSee('erased 2 years after it is closed')
            ->assertSee('11 days after you read them, 22 days');
    }

    public function test_it_tells_what_the_chat_sends_who_can_read_it_and_what_deleting_does(): void
    {
        $this->get(route('privacy'))
            ->assertSee('Your name, e-mail address and account are never sent')
            ->assertSee('Anthropic')
            ->assertSee('Counselors and organizations can not read your chat')
            ->assertSee('Delete account')
            ->assertSee('Personal Data Protection Act 2010');
    }

    public function test_help_and_terms_show_the_helplines_from_the_configuration(): void
    {
        config(['chat.helplines' => [['key' => 'x', 'name' => 'Test Line', 'number' => '12345']]]);

        foreach (['help', 'terms'] as $name) {
            $this->get(route($name))->assertSee('Test Line')->assertSee('tel:12345', false);
        }
    }

    public function test_they_stay_readable_during_maintenance(): void
    {
        $this->assertContains('help', config('platform-settings.maintenance_allowed_routes'));
        $this->assertContains('terms', config('platform-settings.maintenance_allowed_routes'));
        $this->assertContains('privacy', config('platform-settings.maintenance_allowed_routes'));
    }
}
