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

    // ---- the counselor sections (docs/LEGAL_COUNSELOR_DRAFT.md, docs/LEGAL_PAGES.md) -----------------------------------------------------

    public function test_the_counselor_sections_are_there_and_the_pages_are_still_a_draft(): void
    {
        $this->assertTrue((bool) config('legal.draft'), 'these additions are a draft: it stays on until the owner and a lawyer have reviewed the text');

        $this->get(route('terms'))->assertOk()->assertSeeInOrder(['If you are a counselor', 'Who can be a counselor', 'You are independent', 'Your times and your clients', 'Confidentiality', 'Emergencies', 'Organizations', 'Complaints and reviews', 'Ending your account', 'The Feed']);
        $this->get(route('privacy'))->assertOk()->assertSeeInOrder(['If you are a counselor', 'What we keep about counselors', 'Who can see counselor data', 'How long we keep counselor data', 'Your rights as a counselor', 'Deleting your account']);
    }

    public function test_every_number_in_the_counselor_text_comes_from_the_configuration(): void
    {
        config([
            'booking.meeting_link_visible_minutes' => 22, 'session-notes.retention_years' => 9, 'counselors.pa_reminder_days' => [40, 5, 2],
            'counselors.retention.ic_days' => 17, 'counselors.retention.rejected_certificates_days' => 61, 'complaints.retention.closed_years' => 4,
            'notifications.retention.read_days' => 12, 'notifications.retention.unread_days' => 34,
        ]);

        $this->get(route('terms'))
            ->assertSee('The client sees it 22 minutes before the session starts')
            ->assertSee('erased after 9 years')
            ->assertSee('We remind you 40, 5, 2 days before it ends');

        $this->get(route('privacy'))
            ->assertSee('17 days after the final decision')
            ->assertSee('61 days after the rejection')
            ->assertSee('Session notes: 9 years after the session')
            ->assertSee('about you: 4 years after it is closed')
            ->assertSee('12 days after you read them, 34 days');
    }

    public function test_every_fact_still_to_be_confirmed_stays_marked_and_nothing_was_filled_in(): void
    {
        config(['api.support_email' => 'help@example.test']);   // a known address removes ITS marker only

        $terms = [
            'the legal name and address of the organization that runs MyKaunsel',
            'the wording of what MyKaunsel is and is not responsible for',
            'the registration body and the exact professional requirements, with a lawyer',
            'the recording and confidentiality duties, and what a counselor must do if a client may be in danger, with a lawyer',
            'the complaint procedure and how long a counselor has to answer',
            'what is erased and what is kept when a counselor account ends, and for how long',
            'limits of liability and the law that applies, to be written with a lawyer',
        ];
        $privacy = [
            'the legal name and address of the organization that runs MyKaunsel',
            'where the provider keeps the words and for how long, once the contract is final',
            'how long records of important actions are kept, with a lawyer',
            'the contact to write to and how long we take to answer',
            'what happens to a counselor\'s private session notes if the counselor leaves an organization or MyKaunsel',
            'whether Google sign-in stays available to counselors and administrators; today it works for any existing account',
            'the exact notification process and timeline, with a lawyer',
            'the minimum age, and what happens for people under it',
        ];

        $page = $this->get(route('terms'));
        foreach ($terms as $marker) {
            $page->assertSee('To be confirmed: '.$marker);
        }

        $page = $this->get(route('privacy'));
        foreach ($privacy as $marker) {
            $page->assertSee('To be confirmed: '.$marker);
        }
    }

    public function test_the_counselor_text_never_claims_a_live_connection_to_lkm_or_to_a_public_directory(): void
    {
        $terms = $this->get(route('terms'))->getContent();
        $privacy = $this->get(route('privacy'))->getContent();

        $this->assertStringContainsString('registration records we hold', $terms);
        $this->assertStringNotContainsString('records we can reach', $terms);
        $this->assertStringContainsString('we do not have a live connection to a government system', $privacy);

        foreach ([$terms, $privacy] as $page) {
            $this->assertDoesNotMatchRegularExpression('/real-time|official LKM|logged-out|signed-out|without signing in|public directory/i', $page);
        }

        $this->assertStringContainsString('You need to be signed in with a confirmed e-mail address to find a counselor. A counselor who works for a closed organization is shown only to that organization&#039;s members.', $privacy);
    }

    public function test_suspension_and_an_organization_ending_the_assignment_are_two_different_rules(): void
    {
        $page = $this->get(route('terms'))->assertSeeInOrder(['If your profile is suspended', 'If an organization ends your assignment']);
        $text = $page->getContent();

        // Suspension: the whole counselor, every waiting request closed, booked sessions kept when automatic, the team may cancel otherwise.
        $page->assertSee('covers all the places you work')->assertSee('every request waiting for you is closed')
            ->assertSee('Sessions already booked are kept when the suspension is automatic')
            ->assertSee('The times you had already opened are not removed');
        $this->assertStringNotContainsString('in that context', $text);

        // The organization ending the assignment: only that organization, its sessions cancelled, its times and weekly hours removed.
        $page->assertSee('This applies only to that organization')
            ->assertSee('Your later sessions with that organization are cancelled and the clients are told')
            ->assertSee('and you are not suspended');
    }

    public function test_the_two_sentences_that_were_only_true_for_clients_say_who_they_are_for(): void
    {
        $page = $this->get(route('terms'));

        $page->assertSee('As a client, you can belong to only one organization at a time.')
            ->assertSee('(counselors and administrators write to support)')
            ->assertDontSee('<li>You can belong to only one organization at a time.', false);
    }

    public function test_the_counselor_text_describes_how_a_counselor_is_approved_without_an_organization_route(): void
    {
        $page = $this->get(route('terms'));

        $page->assertSee('Every counselor is approved by the MyKaunsel team before being shown to anyone.')
            ->assertSee('An organization can only invite a counselor that MyKaunsel has already approved. The organization does not verify your credentials.');
        $this->assertStringNotContainsString('added directly by an organization', $page->getContent());
    }

    public function test_the_version_and_the_date_of_the_text_were_raised_but_nobody_is_asked_to_agree_again(): void
    {
        $this->assertSame('2026-11-04', config('legal.terms_version'));
        $this->assertSame('4 November 2026', config('legal.updated'));

        $this->get(route('terms'))->assertSee('Last updated: 4 November 2026');
    }

    public function test_they_stay_readable_during_maintenance(): void
    {
        $this->assertContains('help', config('platform-settings.maintenance_allowed_routes'));
        $this->assertContains('terms', config('platform-settings.maintenance_allowed_routes'));
        $this->assertContains('privacy', config('platform-settings.maintenance_allowed_routes'));
    }
}
