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
use App\Services\Chat\AssistantModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAssistant;
use Tests\TestCase;

/** The "Ask anything" box on the Platform Admin dashboard: it asks the model with counts only and returns an answer with real links. */
class PlatformAssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FakeAssistant $ai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ai = new FakeAssistant;
        $this->app->instance(AssistantModel::class, $this->ai);
        $this->admin = User::factory()->create(['name' => 'Secret Admin Name', 'email_verified_at' => now()]);
        $platform = Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->session = ['current_org_id' => $platform->id, 'current_role' => 'platform_admin'];
    }

    private array $session = [];

    private function ask(string $q)
    {
        return $this->actingAs($this->admin)->withSession($this->session)->postJson(route('platform.assistant'), ['question' => $q]);
    }

    public function test_it_answers_and_turns_page_keys_into_real_links(): void
    {
        $this->ai->handler = fn () => '{"answer":"Two organizations are waiting.","pages":["organizations_pending","made_up","complaints_open"]}';

        $res = $this->ask('how many organizations are waiting?')->assertOk();

        $res->assertJsonPath('answer', 'Two organizations are waiting.');
        $this->assertSame([route('platform.organizations', ['tab' => 'pending']), route('platform.complaints', ['tab' => 'open'])], array_column($res->json('links'), 'url'));
    }

    public function test_plain_text_from_the_model_is_still_an_answer(): void
    {
        $this->ai->handler = fn () => 'Nothing is waiting.';

        $this->ask('anything waiting?')->assertOk()->assertJsonPath('answer', 'Nothing is waiting.')->assertJsonPath('links', []);
    }

    public function test_the_model_is_sent_counts_and_the_question_only(): void
    {
        $this->ai->handler = fn () => '{"answer":"ok","pages":[]}';

        $this->ask('how many?')->assertOk();

        $call = $this->ai->calls[0];
        $this->assertStringContainsString('Organizations waiting for approval: 0', $call['system']);
        $this->assertSame([['role' => 'user', 'content' => 'how many?']], $call['messages']);
        $this->assertStringNotContainsString($this->admin->email, $call['system']);
    }

    public function test_a_model_that_is_down_gives_a_friendly_message(): void
    {
        $this->ai->down();

        $this->ask('hello')->assertStatus(503)->assertJsonPath('message', 'The assistant can not answer right now. Please try again in a moment.');
    }

    public function test_an_empty_or_too_long_question_is_refused_and_only_platform_admins_may_ask(): void
    {
        $this->ask('')->assertStatus(422);
        $this->ask(str_repeat('a', 501))->assertStatus(422);
        $this->assertCount(0, $this->ai->calls);

        $client = User::factory()->create(['email_verified_at' => now()]);
        $this->flushSession();
        $this->actingAs($client)->postJson(route('platform.assistant'), ['question' => 'hi'])->assertRedirect();
        $this->assertCount(0, $this->ai->calls);
    }

    public function test_every_page_it_can_point_to_really_opens(): void
    {
        foreach (app(\App\Services\Platform\PlatformAssistant::class)->pages() as $key => [$label, $url]) {
            $this->actingAs($this->admin)->withSession($this->session)->get($url)->assertOk();
        }
    }
}
