<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\ChatSession;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\AssistantModel;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeAssistant;
use Tests\TestCase;

/** `chat:end-idle` and `chat:purge` (docs/CHAT.md). The clock stands at Monday 2 November 2026, 09:00 Malaysian time. */
class ChatCommandsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private FakeAssistant $ai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->ai = new FakeAssistant;
        $this->app->instance(AssistantModel::class, $this->ai);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function chatOf(User $user, \DateTimeInterface $lastMessage, ?string $text = 'I feel low'): ChatSession
    {
        $session = ChatSession::create(['user_id' => $user->id, 'started_at' => $lastMessage, 'last_message_at' => $lastMessage]);

        if ($text !== null) {
            Message::create(['chat_session_id' => $session->id, 'sender' => 'user', 'message_text' => $text, 'sent_at' => $lastMessage]);
            Message::create(['chat_session_id' => $session->id, 'sender' => 'assistant', 'message_text' => 'I hear you.', 'sent_at' => $lastMessage]);
        }

        return $session;
    }

    // ---- chat:end-idle ---------------------------------------------------------------------------------------------

    public function test_only_conversations_quiet_for_long_enough_are_ended_with_their_feeling_and_once(): void
    {
        $user = User::factory()->create();
        $quiet = $this->chatOf($user, now()->subMinutes(61));
        $edge = $this->chatOf($user, now()->subMinutes(60));
        $recent = $this->chatOf($user, now()->subMinutes(59));
        $already = $this->chatOf($user, now()->subDay());
        $already->update(['ended_at' => now()->subHours(20), 'ended_reason' => 'person']);

        $this->artisan('chat:end-idle')->expectsOutput('2 conversation(s) ended.')->assertSuccessful();

        $this->assertNotNull($quiet->fresh()->ended_at);
        $this->assertSame('idle', $quiet->fresh()->ended_reason);
        $this->assertSame('sadness', $quiet->fresh()->dominant_emotion);
        $this->assertNotNull($edge->fresh()->ended_at, 'exactly at the limit counts as quiet');
        $this->assertNull($recent->fresh()->ended_at);
        $this->assertSame('person', $already->fresh()->ended_reason, 'an ended conversation is not touched');
        $this->assertNull($already->fresh()->dominant_emotion);

        $calls = count($this->ai->calls);
        $this->artisan('chat:end-idle')->expectsOutput('0 conversation(s) ended.')->assertSuccessful();
        $this->assertCount($calls, $this->ai->calls);
    }

    public function test_a_model_that_is_down_never_stops_the_conversations_from_ending(): void
    {
        $this->ai->down();
        $user = User::factory()->create();
        $a = $this->chatOf($user, now()->subHours(3));
        $b = $this->chatOf($user, now()->subHours(2));

        $this->artisan('chat:end-idle')->expectsOutput('2 conversation(s) ended.')->assertSuccessful();

        $this->assertNotNull($a->fresh()->ended_at);
        $this->assertNotNull($b->fresh()->ended_at);
        $this->assertNull($a->fresh()->dominant_emotion);
    }

    public function test_a_conversation_with_nothing_the_person_wrote_ends_without_asking_the_model(): void
    {
        $empty = $this->chatOf(User::factory()->create(), now()->subHours(2), null);

        $this->artisan('chat:end-idle')->assertSuccessful();

        $this->assertNotNull($empty->fresh()->ended_at);
        $this->assertSame([], $this->ai->calls);
    }

    // ---- chat:purge ------------------------------------------------------------------------------------------------

    public function test_old_conversations_go_with_their_messages_and_a_system_entry_keeps_only_counts(): void
    {
        $user = User::factory()->create();
        $old = $this->chatOf($user, now()->subMonths(12)->subDay(), 'a secret worry that must not be logged');
        $edge = $this->chatOf($user, now()->subMonths(12)->addMinute());
        $new = $this->chatOf($user, now()->subDay());
        DB::table('emotion_results')->insert(['message_id' => $old->messages()->first()->id, 'emotion' => 'sadness', 'confidence_score' => 0.9, 'analysed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('chat:purge')->expectsOutput('Deleted 1 conversation(s) and 2 message(s).')->assertSuccessful();

        $this->assertNull(ChatSession::find($old->id));
        $this->assertSame(0, Message::where('chat_session_id', $old->id)->count());
        $this->assertSame(0, DB::table('emotion_results')->count());
        $this->assertNotNull(ChatSession::find($edge->id));
        $this->assertNotNull(ChatSession::find($new->id));
        $this->assertSame(4, Message::count());

        $entry = AuditLog::where('action', 'system.chat_purged')->sole();
        $this->assertStringNotContainsString('secret worry', json_encode($entry->toArray()));
        $this->assertSame(1, $entry->metadata['sessions'] ?? $entry->changes['sessions'] ?? null);
    }

    public function test_purge_dry_run_counts_and_changes_nothing_and_a_second_run_finds_nothing(): void
    {
        $this->chatOf(User::factory()->create(), now()->subMonths(13));

        $this->artisan('chat:purge --dry-run')->expectsOutput('Would delete 1 conversation(s).')->assertSuccessful();
        $this->assertSame(1, ChatSession::count());

        $this->artisan('chat:purge')->assertSuccessful();
        $this->artisan('chat:purge')->expectsOutput('Deleted 0 conversation(s) and 0 message(s).')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'system.chat_purged')->count());
    }

    public function test_the_retention_is_the_configured_number_of_months(): void
    {
        config(['chat.retention_months' => 3]);
        $user = User::factory()->create();
        $this->chatOf($user, now()->subMonths(4));
        $keep = $this->chatOf($user, now()->subMonths(2));

        $this->artisan('chat:purge')->assertSuccessful();

        $this->assertSame([$keep->id], ChatSession::pluck('id')->all());
    }

    // ---- scheduled ---------------------------------------------------------------------------------------------------

    public function test_both_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");

        $this->assertStringContainsString('chat:end-idle', $commands);
        $this->assertStringContainsString('chat:purge', $commands);
    }
}
