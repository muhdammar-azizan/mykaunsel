<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\User;
use App\Notifications\FeedContentRemoved;
use App\Notifications\FeedWarning;
use Database\Seeders\PlatformSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Counselor\CounselorTestCase;

/** Platform Admin → Moderate Feed (docs/FEED.md): the queue of reports, setting them aside, removing, warning. */
class ModerateFeedTest extends CounselorTestCase
{
    private User $author;

    private FeedPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        $this->signInAdmin();

        $this->author = User::factory()->create(['name' => 'Aiman Writer']);
        $this->post = FeedPost::create(['user_id' => $this->author->id, 'content' => 'A rude thing it said']);
    }

    private function report(FeedPost|FeedComment $target, string $reason = 'spam', ?User $by = null): FeedReport
    {
        return FeedReport::create([
            'user_id' => ($by ?? User::factory()->create(['name' => 'Secret Reporter']))->id,
            ($target instanceof FeedPost ? 'feed_post_id' : 'feed_comment_id') => $target->id,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    private function audit(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    public function test_guests_and_other_people_cannot_open_it(): void
    {
        $this->get(route('platform.moderation'))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('platform.moderation'))->assertRedirect();
    }

    public function test_a_platform_admin_opens_it(): void
    {
        $this->as()->get(route('platform.moderation'))->assertOk()->assertSee('Nothing to review');
    }

    public function test_the_queue_groups_reports_by_post_shows_the_writing_and_never_the_reporters(): void
    {
        $this->report($this->post, 'spam');
        $this->report($this->post, 'harassment');
        $comment = FeedComment::create(['feed_post_id' => $this->post->id, 'user_id' => $this->author->id, 'content' => 'Mean comment here']);
        $this->report($comment, 'other');

        $page = $this->as()->get(route('platform.moderation'))->assertOk()
            ->assertSee('A rude thing it said')->assertSee('Mean comment here')->assertSee('Aiman Writer')
            ->assertSee('2 reports')->assertSee('1 report')
            ->assertSee('Spam × 1')->assertSee('Harassment or bullying × 1')
            ->assertDontSee('Secret Reporter');

        $page->assertSee('Moderate Feed');
        $this->as()->get(route('platform.dashboard'))->assertDontSee('Moderate Feed</span>', false);
    }

    public function test_setting_reports_aside_keeps_the_post_and_moves_it_to_handled(): void
    {
        $this->report($this->post);
        $this->report($this->post, 'other');

        $this->as()->post(route('platform.moderation.dismiss', ['post', $this->post->id]))->assertRedirect()->assertSessionHas('status');

        $this->assertNull($this->post->fresh()->removed_at);
        $this->assertSame(0, FeedReport::where('status', 'pending')->count());
        $this->assertSame(2, FeedReport::where('status', 'dismissed')->where('handled_by_user_id', $this->admin->id)->count());

        $log = $this->audit('moderation.report_dismissed')->sole();
        $this->assertSame('feed_post', $log->target_type);
        $this->assertSame($this->post->id, $log->target_id);

        $this->as()->get(route('platform.moderation'))->assertSee('Nothing to review');
        $this->as()->get(route('platform.moderation', ['tab' => 'handled']))->assertSee('A rude thing it said')->assertSee('Reports set aside');
    }

    public function test_a_second_admin_acting_on_the_same_row_is_told_and_nothing_changes(): void
    {
        $this->report($this->post);

        $this->as()->post(route('platform.moderation.dismiss', ['post', $this->post->id]));
        $this->as()->post(route('platform.moderation.remove', ['post', $this->post->id]))->assertSessionHas('error');

        $this->assertNull($this->post->fresh()->removed_at);
        $this->assertCount(0, $this->audit('moderation.post_removed'));
    }

    public function test_removing_a_post_hides_it_for_everyone_tells_the_author_and_is_audited_without_the_text(): void
    {
        Notification::fake();
        $this->report($this->post);

        $this->as()->post(route('platform.moderation.remove', ['post', $this->post->id]), ['note' => 'Please keep it kind.'])->assertSessionHas('status');

        $this->assertNotNull($this->post->fresh()->removed_at);
        $this->assertCount(0, FeedPost::query()->visible()->get());
        $this->assertSame(1, FeedReport::where('status', 'actioned')->count());
        Notification::assertSentTo($this->author, FeedContentRemoved::class);

        $log = $this->audit('moderation.post_removed')->sole();
        $this->assertStringNotContainsString('rude', json_encode($log->metadata));
        $this->assertStringNotContainsString('kind', json_encode($log->metadata));
    }

    public function test_removing_a_comment_is_audited_as_a_comment(): void
    {
        Notification::fake();
        $comment = FeedComment::create(['feed_post_id' => $this->post->id, 'user_id' => $this->author->id, 'content' => 'Bad comment']);
        $this->report($comment);

        $this->as()->post(route('platform.moderation.remove', ['comment', $comment->id]))->assertSessionHas('status');

        $this->assertNotNull($comment->fresh()->removed_at);
        $this->assertNull($this->post->fresh()->removed_at);
        $this->assertSame('feed_comment', $this->audit('moderation.comment_removed')->sole()->target_type);
        Notification::assertSentTo($this->author, FeedContentRemoved::class);
    }

    public function test_a_warning_needs_a_message_keeps_the_post_and_emails_the_author(): void
    {
        Notification::fake();
        $this->report($this->post);

        $this->as()->post(route('platform.moderation.warn', ['post', $this->post->id]), ['message' => 'short'])->assertSessionHasErrorsIn('warnpost'.$this->post->id, 'message');
        $this->assertSame(1, FeedReport::where('status', 'pending')->count());

        $this->as()->post(route('platform.moderation.warn', ['post', $this->post->id]), ['message' => 'Please be kind to others here.'])->assertSessionHas('status');

        $this->assertNull($this->post->fresh()->removed_at);
        $this->assertSame(1, FeedReport::where('status', 'actioned')->count());
        Notification::assertSentTo($this->author, FeedWarning::class, fn ($n) => true);

        $log = $this->audit('moderation.user_warned')->sole();
        $this->assertSame('user', $log->target_type);
        $this->assertSame($this->author->id, $log->target_id);
        $this->assertStringNotContainsString('kind to others', json_encode($log->metadata));
    }

    public function test_a_post_its_author_already_deleted_is_shown_as_such_and_can_still_be_set_aside(): void
    {
        $this->report($this->post);
        $this->post->update(['deleted_at' => now()]);

        $this->as()->get(route('platform.moderation'))->assertSee('Deleted by its author');
        $this->as()->post(route('platform.moderation.dismiss', ['post', $this->post->id]))->assertSessionHas('status');
    }

    public function test_the_kind_in_the_address_must_be_post_or_comment(): void
    {
        $this->as()->post('/platform/moderation/user/1/dismiss')->assertNotFound();
    }
}
