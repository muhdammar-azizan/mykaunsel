<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewOrganizationApplication;
use App\Services\PlatformAdminNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class OrganizationApplicationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = Organization::create([
            'name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active,
        ]);
    }

    private function platformAdmin(string $name, array $prefs = null, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create(['name' => $name, 'notification_preferences' => $prefs]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    private function signupPayload(array $overrides = []): array
    {
        return $overrides + [
            'org_type' => 'university',
            'org_name' => 'Universiti Baharu',
            'org_size' => '1,000 - 5,000',
            'access_model' => 'closed',
            'domains' => [['domain' => 'baharu.edu.my', 'role' => 'Student']],
            'admin_name' => 'Aminah Baharu',
            'admin_title' => 'Registrar',
            'admin_email' => 'aminah@baharu.edu.my',
            'admin_password' => 'Secret#Pass99',
            'admin_password_confirmation' => 'Secret#Pass99',
            'confirm_authorized' => 1,
            'confirm_terms' => 1,
        ];
    }

    public function test_every_platform_admin_who_wants_it_is_emailed_when_an_organization_registers(): void
    {
        Notification::fake();
        $a = $this->platformAdmin('Admin A');                                   // default: on
        $b = $this->platformAdmin('Admin B', ['new_organization_application' => true]);

        $this->post(route('organizations.signup.store'), $this->signupPayload())->assertRedirect();

        $org = Organization::where('name', 'Universiti Baharu')->firstOrFail();
        Notification::assertSentTo([$a, $b], NewOrganizationApplication::class, fn ($n) => true);
        Notification::assertSentTimes(NewOrganizationApplication::class, 2);
        $this->assertSame(OrgStatus::Pending, $org->subscription_status);
    }

    public function test_admins_who_turned_it_off_or_are_not_active_admins_are_not_emailed(): void
    {
        Notification::fake();
        $off = $this->platformAdmin('Admin Off', ['new_organization_application' => false]);
        $suspended = $this->platformAdmin('Admin Digantung', null, MembershipStatus::Suspended);
        $other = User::factory()->create();
        Membership::create(['user_id' => $other->id, 'org_id' => $this->platform->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $on = $this->platformAdmin('Admin On');

        $this->post(route('organizations.signup.store'), $this->signupPayload());

        Notification::assertSentTo($on, NewOrganizationApplication::class);
        Notification::assertNotSentTo([$off, $suspended, $other], NewOrganizationApplication::class);
        Notification::assertSentTimes(NewOrganizationApplication::class, 1);
    }

    public function test_the_other_notification_switches_do_not_affect_it(): void
    {
        Notification::fake();
        $admin = $this->platformAdmin('Admin', ['new_counselor_application' => false, 'weekly_summary' => false, 'new_organization_application' => true]);

        $this->post(route('organizations.signup.store'), $this->signupPayload());

        Notification::assertSentTo($admin, NewOrganizationApplication::class);
    }

    public function test_the_email_has_the_details_and_a_link_to_the_review_page(): void
    {
        $admin = $this->platformAdmin('Admin A');
        Notification::fake();

        $this->post(route('organizations.signup.store'), $this->signupPayload());
        $org = Organization::where('name', 'Universiti Baharu')->firstOrFail();

        Notification::assertSentTo($admin, NewOrganizationApplication::class, function ($n) use ($admin, $org) {
            $mail = $n->toMail($admin);
            $text = $mail->subject.' '.implode(' ', $mail->introLines);

            return str_contains($text, 'Universiti Baharu')
                && str_contains($text, 'university')
                && str_contains($text, 'Aminah Baharu (aminah@baharu.edu.my)')
                && str_contains($text, 'baharu.edu.my')
                && $mail->actionUrl === route('platform.organizations.show', $org)
                && collect($mail->outroLines)->contains(fn ($l) => str_contains($l, 'Account → Notifications'));
        });
    }

    public function test_the_real_email_renders_and_is_sent_without_errors(): void
    {
        $this->platformAdmin('Admin A');

        $this->post(route('organizations.signup.store'), $this->signupPayload())->assertRedirect();

        $this->assertNotNull(Organization::where('name', 'Universiti Baharu')->first());
    }

    public function test_signup_still_succeeds_when_the_mail_server_is_down(): void
    {
        $this->platformAdmin('Admin A');
        \Illuminate\Support\Facades\Log::spy();

        // A mail transport that always fails.
        $this->app->make(MailManager::class)->extend('broken', fn () => new class extends AbstractTransport
        {
            protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void
            {
                throw new \RuntimeException('mail server down');
            }

            public function __toString(): string
            {
                return 'broken';
            }
        });
        config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'broken']]);

        $this->post(route('organizations.signup.store'), $this->signupPayload())
            ->assertRedirect(route('organizations.signup.verify-domain', Organization::where('name', 'Universiti Baharu')->firstOrFail()));

        $this->assertDatabaseHas('organizations', ['name' => 'Universiti Baharu', 'subscription_status' => 'pending']);
        $this->assertDatabaseHas('users', ['email' => 'aminah@baharu.edu.my']);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once(); // the failure was caught and logged
    }

    public function test_signup_works_when_there_are_no_platform_admins(): void
    {
        Notification::fake();

        $this->post(route('organizations.signup.store'), $this->signupPayload())->assertRedirect();

        Notification::assertNothingSent();
        $this->assertNotNull(Organization::where('name', 'Universiti Baharu')->first());
    }

    public function test_a_failed_signup_notifies_nobody(): void
    {
        Notification::fake();
        $this->platformAdmin('Admin A');

        $this->post(route('organizations.signup.store'), $this->signupPayload(['admin_email' => 'bukan-emel']))->assertSessionHasErrors('admin_email');

        Notification::assertNothingSent();
        $this->assertSame(0, Organization::where('name', 'Universiti Baharu')->count());
    }

    public function test_the_notifier_reports_how_many_admins_were_emailed(): void
    {
        Notification::fake();
        $this->platformAdmin('Admin A');
        $this->platformAdmin('Admin B', ['new_organization_application' => false]);
        $org = Organization::create(['name' => 'Org', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Pending]);

        $this->assertSame(1, app(PlatformAdminNotifier::class)->newOrganizationApplication($org));
    }
}
