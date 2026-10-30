<?php

namespace Tests\Feature\Api;

use App\Models\ChatSession;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\AnthropicAssistantModel;
use App\Services\Chat\AssistantModel;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAssistant;
use Tests\TestCase;

/**
 * The chatbot's API (docs/CHAT.md): consent, sending and the answer, crisis detection on the server, what the model is sent, a model that can't
 * answer, repeats, ending with a dominant emotion, history, limits. The model is always a fake: no test calls the network. The clock stands at
 * Monday 2 November 2026, 09:00 Malaysian time.
 */
class ChatApiTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

    private FakeAssistant $ai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->ai = new FakeAssistant;
        $this->app->instance(AssistantModel::class, $this->ai);
        $this->me = $this->signedIn();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function signedIn(bool $consent = true, string $name = 'Siti Aminah'): User
    {
        static $n = 0;
        $user = User::factory()->create(['name' => $name, 'email' => str($name)->slug().'-'.(++$n).'@example.test', 'email_verified_at' => now(), 'ai_consent_at' => $consent ? now() : null]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function say(string $text, ?string $session = null, array $over = [])
    {
        return $this->postJson('/api/v1/chat/messages', $over + array_filter(['text' => $text, 'session' => $session], fn ($v) => $v !== null));
    }

    // ---- who may ask ---------------------------------------------------------------------------------------------

    public function test_signed_out_gets_401_unconfirmed_email_403_and_maintenance_503(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/chat/messages', ['text' => 'hi'])->assertUnauthorized();
        $this->getJson('/api/v1/chat/sessions')->assertUnauthorized();

        $user = User::factory()->create(['email_verified_at' => null, 'ai_consent_at' => now()]);
        Sanctum::actingAs($user);
        $this->say('hi')->assertForbidden()->assertJson(['code' => 'email_not_verified']);

        $this->signedIn();
        app(PlatformSettings::class)->setMaintenance(null, true);
        $this->say('hi')->assertStatus(503);
        $this->assertSame(0, Message::count());
        $this->assertSame([], $this->ai->calls);
    }

    public function test_the_helplines_need_no_sign_in_and_no_consent(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/chat/helplines')->assertOk()
            ->assertJsonPath('helplines.0.name', 'HEAL')->assertJsonPath('helplines.0.number', '15555')
            ->assertJsonPath('helplines.1.name', 'Talian Kasih')->assertJsonPath('helplines.1.number', '15999');
    }

    // ---- consent ---------------------------------------------------------------------------------------------------

    public function test_without_consent_nothing_is_stored_and_nothing_is_sent(): void
    {
        $this->signedIn(consent: false, name: 'No Consent');

        $this->say('I feel low')->assertForbidden()->assertJson(['code' => 'ai_consent_required']);

        $this->assertSame(0, ChatSession::count());
        $this->assertSame(0, Message::count());
        $this->assertSame([], $this->ai->calls);
    }

    public function test_consent_is_given_and_withdrawn_in_settings_and_every_change_is_recorded(): void
    {
        $user = $this->signedIn(consent: false, name: 'Fresh Person');

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.ai_consent', false);

        $this->putJson('/api/v1/account/ai-consent', ['consent' => true])->assertOk()->assertJsonPath('user.ai_consent', true);
        $this->assertNotNull($user->fresh()->ai_consent_at);
        Sanctum::actingAs($user->fresh()); // a real request loads the person afresh; the test's actor is the same object each time
        $this->say('hello')->assertOk();

        $this->putJson('/api/v1/account/ai-consent', ['consent' => false])->assertOk()->assertJsonPath('user.ai_consent', false);
        $this->assertNull($user->fresh()->ai_consent_at);
        Sanctum::actingAs($user->fresh());
        $this->say('hello again')->assertForbidden()->assertJson(['code' => 'ai_consent_required']);

        $events = DB::table('ai_consent_events')->where('user_id', $user->id)->orderBy('id')->get();
        $this->assertSame([1, 0], $events->pluck('granted')->map(fn ($g) => (int) $g)->all());
        $this->assertSame('app', $events->first()->source);
    }

    public function test_asking_for_the_state_already_held_changes_and_records_nothing(): void
    {
        $this->putJson('/api/v1/account/ai-consent', ['consent' => true])->assertOk();
        $this->putJson('/api/v1/account/ai-consent', ['consent' => true])->assertOk();

        $this->assertSame(0, DB::table('ai_consent_events')->count());
        $this->putJson('/api/v1/account/ai-consent', [])->assertStatus(422)->assertJsonValidationErrors('consent');
    }

    public function test_withdrawing_consent_leaves_the_history_readable(): void
    {
        $id = $this->say('hello')->assertOk()->json('session.id');
        $this->putJson('/api/v1/account/ai-consent', ['consent' => false])->assertOk();
        Sanctum::actingAs($this->me->fresh());

        $this->getJson('/api/v1/chat/sessions/'.$id)->assertOk()->assertJsonCount(2, 'session.messages');
    }

    // ---- sending ---------------------------------------------------------------------------------------------------

    public function test_a_message_starts_a_conversation_and_the_answer_comes_back_with_it(): void
    {
        $response = $this->say('  I feel low today.  ')->assertOk();

        $response->assertJsonPath('message.sender', 'user')->assertJsonPath('message.text', 'I feel low today.')->assertJsonPath('message.crisis', false)
            ->assertJsonPath('reply.sender', 'assistant')->assertJsonPath('reply.text', 'I hear you. That sounds hard.')
            ->assertJsonPath('assistant_unavailable', false)->assertJsonPath('crisis', false)->assertJsonPath('helplines', [])->assertJsonPath('suggestion', null)
            ->assertJsonPath('session.ended', false)->assertJsonPath('session.dominant_emotion', null);
        $this->assertSame(26, strlen($response->json('session.id')));
        $this->assertSame(26, strlen($response->json('message.id')));
        $this->assertSame(1, ChatSession::count());
        $this->assertSame(2, Message::count());
        $this->assertSame($this->me->id, ChatSession::sole()->user_id);
        $response->assertHeader('Cache-Control');
    }

    public function test_the_next_message_joins_the_same_conversation_and_the_model_sees_the_earlier_ones(): void
    {
        $id = $this->say('I feel low today.')->json('session.id');

        $this->say('It is because of exams.', $id)->assertOk()->assertJsonPath('session.id', $id);

        $this->assertSame(1, ChatSession::count());
        $this->assertSame(4, Message::count());
        $sent = $this->ai->replyCalls()[1]['messages'];
        $this->assertSame(['user', 'assistant', 'user'], array_column($sent, 'role'));
        $this->assertSame('It is because of exams.', $sent[2]['content']);
    }

    public function test_the_model_is_never_told_who_the_person_is_and_personal_details_are_taken_out_of_what_it_is_sent(): void
    {
        $this->say('Call me on 012-345 6789 or write to siti@example.test, my ic is 990101-06-5432, see https://x.example/me')->assertOk();

        $call = $this->ai->replyCalls()[0];
        $sent = json_encode($call);

        $this->assertStringNotContainsString('Siti', $sent);
        $this->assertStringNotContainsString($this->me->email, $sent);
        $this->assertStringNotContainsString('012-345', $sent);
        $this->assertStringNotContainsString('990101', $sent);
        $this->assertStringNotContainsString('x.example', $sent);
        $this->assertSame('Call me on [phone] or write to [email], my ic is [id number], see [link]', $call['messages'][0]['content']);
        $this->assertSame((int) config('chat.reply_max_tokens'), $call['maxTokens']);

        // What the person wrote is kept as they wrote it.
        $this->assertStringContainsString('012-345 6789', Message::where('sender', 'user')->sole()->message_text);
    }

    public function test_the_prompt_is_a_companion_not_a_counselor_and_names_nobody(): void
    {
        $this->say('hi')->assertOk();

        $system = $this->ai->replyCalls()[0]['system'];
        $this->assertStringContainsString('You are an AI, not a counselor', $system);
        $this->assertStringContainsString('Never describe methods', $system);
        $this->assertStringNotContainsString('latest message may mean they are in danger', $system, 'the crisis note is only for a flagged message');
    }

    public function test_only_the_latest_messages_go_to_the_model_and_the_first_is_always_the_persons(): void
    {
        config(['chat.history_messages' => 3]);
        $id = $this->say('one')->json('session.id');
        $this->say('two', $id);
        $this->say('three', $id);

        $sent = $this->ai->replyCalls()[2]['messages'];
        $this->assertCount(3, $sent);
        $this->assertSame('user', $sent[0]['role']);
        $this->assertSame('three', end($sent)['content']);
    }

    public function test_a_message_must_have_text_and_not_be_too_long(): void
    {
        $this->postJson('/api/v1/chat/messages', [])->assertStatus(422)->assertJsonValidationErrors('text');
        $this->say('   ')->assertStatus(422);
        $this->say(str_repeat('a', (int) config('chat.max_message_chars') + 1))->assertStatus(422)->assertJsonValidationErrors('text');
        $this->say('ok', null, ['client_id' => 'not-a-uuid'])->assertStatus(422)->assertJsonValidationErrors('client_id');
        $this->say(str_repeat('a', (int) config('chat.max_message_chars')))->assertOk();
    }

    // ---- crisis ------------------------------------------------------------------------------------------------------

    public function test_a_worrying_message_is_flagged_by_the_server_with_the_helplines_and_the_conversation_goes_on(): void
    {
        $response = $this->say('I do not want to live anymore')->assertOk();

        $response->assertJsonPath('crisis', true)->assertJsonPath('message.crisis', true)->assertJsonPath('reply.sender', 'assistant')
            ->assertJsonPath('helplines.0.name', 'HEAL')->assertJsonPath('helplines.1.number', '15999');
        $this->assertTrue(Message::where('sender', 'user')->sole()->crisis);
        $this->assertFalse(Message::where('sender', 'assistant')->sole()->crisis);
        $this->assertStringContainsString('may mean they are in danger', $this->ai->replyCalls()[0]['system']);

        // It does not block: the next message is answered like any other.
        $id = $response->json('session.id');
        $this->say('thank you', $id)->assertOk()->assertJsonPath('crisis', false)->assertJsonPath('reply.sender', 'assistant');
    }

    public function test_the_flag_does_not_depend_on_the_model(): void
    {
        $this->ai->down();

        $response = $this->say('saya nak bunuh diri')->assertOk();

        $response->assertJsonPath('crisis', true)->assertJsonPath('assistant_unavailable', true)->assertJsonPath('reply', null)
            ->assertJsonPath('helplines.0.name', 'HEAL');
    }

    public function test_a_flagged_message_also_offers_counselors_the_person_may_book(): void
    {
        $this->counselor('Aina', [$this->platform]);

        $response = $this->say('I want to die')->assertOk();

        $response->assertJsonPath('suggestion.counselors.0.name', 'Aina');
        $this->assertLessThanOrEqual(3, count($response->json('suggestion.counselors')));
        $this->assertStringNotContainsString('kb_number', $response->content());
    }

    public function test_asking_for_a_counselor_offers_them_and_an_ordinary_message_does_not(): void
    {
        $this->counselor('Aina', [$this->platform]);

        $this->say('I would like to see a counselor')->assertOk()->assertJsonPath('suggestion.counselors.0.name', 'Aina');
        $this->say('saya nak jumpa kaunselor')->assertOk()->assertJsonPath('suggestion.counselors.0.name', 'Aina');
        $this->say('I had a rough day')->assertOk()->assertJsonPath('suggestion', null);
    }

    public function test_no_counselors_to_offer_is_no_suggestion(): void
    {
        $this->say('I want to see a counselor')->assertOk()->assertJsonPath('suggestion', null);
    }

    // ---- a model that can not answer ----------------------------------------------------------------------------------------------

    public function test_when_the_model_is_down_the_message_is_kept_and_the_answer_can_be_asked_for_again(): void
    {
        $this->ai->down();
        $first = $this->say('I feel low')->assertOk()->assertJsonPath('assistant_unavailable', true)->assertJsonPath('reply', null)->assertJsonPath('message.text', 'I feel low');
        $id = $first->json('session.id');
        $this->assertSame(1, Message::count());

        $this->ai = new FakeAssistant;
        $this->app->instance(AssistantModel::class, $this->ai);

        $again = $this->postJson('/api/v1/chat/sessions/'.$id.'/reply')->assertOk();
        $again->assertJsonPath('assistant_unavailable', false)->assertJsonPath('reply.text', 'I hear you. That sounds hard.')->assertJsonPath('message.id', $first->json('message.id'));
        $this->assertSame(2, Message::count(), 'the person\'s message was not stored twice');

        // Asking once more returns the answer that exists and asks the model nothing.
        $calls = count($this->ai->calls);
        $this->postJson('/api/v1/chat/sessions/'.$id.'/reply')->assertOk()->assertJsonPath('reply.id', $again->json('reply.id'));
        $this->assertCount($calls, $this->ai->calls);
        $this->assertSame(2, Message::count());
    }

    public function test_with_no_key_the_feature_is_off_and_the_person_gets_the_friendly_answer(): void
    {
        $this->app->bind(AssistantModel::class, AnthropicAssistantModel::class);
        config(['services.anthropic.key' => '']);

        $this->say('hello')->assertOk()->assertJsonPath('assistant_unavailable', true)->assertJsonPath('reply', null);
        $this->assertSame(1, Message::count());
    }

    public function test_an_empty_answer_from_the_model_is_not_stored(): void
    {
        $this->ai->handler = fn () => throw new \App\Services\Chat\AssistantUnavailable('empty');

        $this->say('hi')->assertOk()->assertJsonPath('assistant_unavailable', true);
        $this->assertSame(0, Message::where('sender', 'assistant')->count());
    }

    // ---- repeats -------------------------------------------------------------------------------------------------------------------

    public function test_sending_the_same_message_again_after_a_lost_answer_stores_nothing_twice(): void
    {
        $clientId = (string) Str::uuid();
        $first = $this->say('hello', null, ['client_id' => $clientId])->assertOk();
        $id = $first->json('session.id');

        $again = $this->say('hello', $id, ['client_id' => $clientId])->assertOk();

        $this->assertSame($first->json('message.id'), $again->json('message.id'));
        $this->assertSame($first->json('reply.id'), $again->json('reply.id'));
        $this->assertSame(2, Message::count());
        $this->assertCount(1, $this->ai->replyCalls());
    }

    public function test_the_same_client_id_in_another_conversation_is_a_new_message(): void
    {
        $clientId = (string) Str::uuid();
        $this->say('hello', null, ['client_id' => $clientId])->assertOk();
        $this->say('hello', null, ['client_id' => $clientId])->assertOk();

        $this->assertSame(2, ChatSession::count());
    }

    // ---- whose conversation --------------------------------------------------------------------------------------------------------------

    public function test_another_persons_conversation_and_an_unknown_one_are_the_same_404(): void
    {
        $theirs = $this->say('mine')->json('session.id');
        $this->signedIn(name: 'Someone Else');
        $unknown = '01hzzzzzzzzzzzzzzzzzzzzzzz';

        $a = $this->getJson('/api/v1/chat/sessions/'.$theirs);
        $a->assertNotFound()->assertJson(['code' => 'session_not_found']);
        $this->assertSame($a->content(), $this->getJson('/api/v1/chat/sessions/'.$unknown)->content());
        $this->say('hi', $theirs)->assertNotFound()->assertJson(['code' => 'session_not_found']);
        $this->postJson('/api/v1/chat/sessions/'.$theirs.'/reply')->assertNotFound();
        $this->postJson('/api/v1/chat/sessions/'.$theirs.'/end')->assertNotFound();
        $this->assertNull(ChatSession::where('public_id', $theirs)->value('ended_at'));
        $this->assertSame(2, Message::count());
    }

    // ---- ending ---------------------------------------------------------------------------------------------------------------------------

    public function test_ending_stores_the_dominant_emotion_and_ends_the_conversation(): void
    {
        $id = $this->say('I feel low and alone')->json('session.id');

        $this->postJson('/api/v1/chat/sessions/'.$id.'/end')->assertOk()->assertJsonPath('session.ended', true)->assertJsonPath('session.dominant_emotion', 'sadness');

        $session = ChatSession::sole();
        $this->assertNotNull($session->ended_at);
        $this->assertSame('person', $session->ended_reason);
        $this->assertSame('sadness', $session->dominant_emotion);

        // The model was asked only about what the PERSON wrote, with the feelings it may choose from.
        $call = end($this->ai->calls);
        $this->assertStringContainsString('sadness', $call['system']);
        $this->assertSame([['role' => 'user', 'content' => 'I feel low and alone']], $call['messages']);

        $this->say('more', $id)->assertStatus(409)->assertJson(['code' => 'session_ended']);
    }

    public function test_ending_twice_changes_nothing_and_asks_the_model_nothing(): void
    {
        $id = $this->say('hello')->json('session.id');
        $this->postJson('/api/v1/chat/sessions/'.$id.'/end')->assertOk();
        $ended = ChatSession::sole()->ended_at;
        $calls = count($this->ai->calls);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->postJson('/api/v1/chat/sessions/'.$id.'/end')->assertOk()->assertJsonPath('session.dominant_emotion', 'sadness');

        $this->assertEquals($ended, ChatSession::sole()->ended_at);
        $this->assertCount($calls, $this->ai->calls);
    }

    public function test_a_feeling_not_on_the_list_or_a_model_that_is_down_leaves_it_empty_but_the_conversation_still_ends(): void
    {
        $a = $this->say('hello')->json('session.id');
        $this->ai->handler = fn (string $system) => str_contains($system, 'ONE feeling') ? 'Ecstatic!' : 'ok';
        $this->postJson('/api/v1/chat/sessions/'.$a.'/end')->assertOk()->assertJsonPath('session.ended', true)->assertJsonPath('session.dominant_emotion', null);

        $b = $this->say('hello')->json('session.id');
        $this->ai->down();
        $this->postJson('/api/v1/chat/sessions/'.$b.'/end')->assertOk()->assertJsonPath('session.ended', true)->assertJsonPath('session.dominant_emotion', null);
    }

    public function test_the_feeling_is_taken_from_the_first_word_whatever_the_case_or_the_full_stop(): void
    {
        $id = $this->say('hello')->json('session.id');
        $this->ai->handler = fn (string $system) => str_contains($system, 'ONE feeling') ? "Anxiety.\nBecause of exams" : 'ok';

        $this->postJson('/api/v1/chat/sessions/'.$id.'/end')->assertOk()->assertJsonPath('session.dominant_emotion', 'anxiety');
    }

    // ---- history ----------------------------------------------------------------------------------------------------------------------------

    public function test_the_history_lists_the_persons_own_conversations_newest_first_with_a_preview(): void
    {
        $old = $this->say('first conversation, a rather long first sentence that goes on and on and on past the preview limit of eighty characters')->json('session.id');
        Carbon::setTestNow(now()->addDay());
        $new = $this->say('second one')->json('session.id');
        $this->signedIn(name: 'Another Person');
        $this->say('not yours');
        Sanctum::actingAs($this->me);

        $list = $this->getJson('/api/v1/chat/sessions')->assertOk()->assertJsonPath('page', 1)->assertJsonPath('last_page', 1)->assertJsonCount(2, 'data');

        $this->assertSame([$new, $old], array_column($list->json('data'), 'id'));
        $this->assertSame('second one', $list->json('data.0.preview'));
        $this->assertLessThanOrEqual(80, mb_strlen($list->json('data.1.preview')));
        $this->assertStringEndsWith('…', $list->json('data.1.preview'));
    }

    public function test_one_conversation_is_read_whole_in_order(): void
    {
        $id = $this->say('one')->json('session.id');
        $this->say('two', $id);

        $messages = $this->getJson('/api/v1/chat/sessions/'.$id)->assertOk()->json('session.messages');

        $this->assertSame(['user', 'assistant', 'user', 'assistant'], array_column($messages, 'sender'));
        $this->assertSame('one', $messages[0]['text']);
        $this->assertStringContainsString('+08:00', $messages[0]['sent_at']);
    }

    public function test_the_history_is_paged(): void
    {
        for ($i = 0; $i < 21; $i++) {
            ChatSession::create(['user_id' => $this->me->id, 'started_at' => now()->subMinutes($i), 'last_message_at' => now()->subMinutes($i)]);
        }

        $this->getJson('/api/v1/chat/sessions')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('last_page', 2);
        $this->getJson('/api/v1/chat/sessions?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    // ---- limits ----------------------------------------------------------------------------------------------------------------------------

    public function test_a_conversation_is_full_after_the_most_messages(): void
    {
        config(['chat.max_session_messages' => 4]);
        $id = $this->say('one')->json('session.id');
        $this->say('two', $id)->assertOk();

        $this->say('three', $id)->assertStatus(409)->assertJson(['code' => 'session_full']);
        $this->assertSame(4, Message::count());
    }

    public function test_there_is_a_daily_limit_with_a_wait_until_tomorrow(): void
    {
        config(['chat.daily_messages' => 2]);
        $id = $this->say('one')->json('session.id');
        $this->say('two', $id)->assertOk();

        $limited = $this->say('three', $id)->assertStatus(429)->assertJson(['code' => 'chat_daily_limit'])->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
        $this->assertSame(4, Message::count());

        // The next day it works again.
        Carbon::setTestNow(Carbon::parse('2026-11-03 00:01', 'Asia/Kuala_Lumpur'));
        $this->say('three', $id)->assertOk();
    }

    public function test_sending_is_rate_limited_with_retry_after(): void
    {
        config(['chat.rate_per_minute' => 2]);
        $id = $this->say('one')->json('session.id');
        $this->say('two', $id)->assertOk();

        $this->say('three', $id)->assertStatus(429)->assertHeader('Retry-After');
    }

    // ---- the model is never held up by a lock -----------------------------------------------------------------------------------

    public function test_the_model_is_asked_after_the_message_is_stored_and_outside_any_transaction(): void
    {
        $levels = [];
        $stored = [];
        $base = DB::transactionLevel(); // the test itself runs inside one
        $this->ai->handler = function () use (&$levels, &$stored) {
            $levels[] = DB::transactionLevel();
            $stored[] = Message::where('sender', 'user')->count();

            return 'ok';
        };

        $this->say('hello')->assertOk();

        $this->assertSame([$base], $levels, 'no transaction of the chat (so no lock) is open while the model works');
        $this->assertSame([1], $stored, 'the person\'s message was already stored');
    }
}
