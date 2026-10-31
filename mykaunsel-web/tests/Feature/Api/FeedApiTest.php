<?php

namespace Tests\Feature\Api;

use App\Enums\AccessModel;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The wellness Feed API (docs/FEED.md): ordinary posts under the author's own name, likes, comments, reports, mutes, the crisis flag, repeats, limits.
 * The clock stands at Monday 2 November 2026, 09:00 Malaysian time.
 */
class FeedApiTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->me = $this->as('Siti Aminah');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(string $name): User
    {
        static $n = 0;
        $user = User::factory()->create(['name' => $name, 'email' => 'u'.(++$n).'@example.test', 'email_verified_at' => now()]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function write(string $text = 'Hello everyone', array $over = [])
    {
        return $this->postJson('/api/v1/feed/posts', ['text' => $text, 'client_id' => (string) Str::uuid()] + $over);
    }

    private function postId(string $text = 'Hello everyone'): string
    {
        return $this->write($text)->assertCreated()->json('post.id');
    }

    public function test_it_needs_a_signed_in_verified_person(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/feed')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['email_verified_at' => null]));
        $this->getJson('/api/v1/feed')->assertForbidden();
    }

    public function test_a_post_shows_the_author_name_and_is_theirs(): void
    {
        $this->write('Feeling hopeful today')->assertCreated()
            ->assertJsonPath('post.text', 'Feeling hopeful today')
            ->assertJsonPath('post.author.name', 'Siti Aminah')
            ->assertJsonPath('post.author.counselor', false)
            ->assertJsonPath('post.mine', true)
            ->assertJsonPath('post.reactions.count', 0)
            ->assertJsonPath('post.comments_count', 0)
            ->assertJsonPath('post.edited', false)
            ->assertJsonPath('crisis', false)
            ->assertJsonPath('helplines', []);

        $this->as('Aiman');
        $this->getJson('/api/v1/feed')->assertOk()
            ->assertJsonPath('data.0.author.name', 'Siti Aminah')
            ->assertJsonPath('data.0.mine', false);
    }

    public function test_the_response_never_exposes_internal_ids_or_emails(): void
    {
        $this->write();
        $body = $this->getJson('/api/v1/feed')->assertOk()->getContent();

        $this->assertStringNotContainsString('example.test', $body);
        $this->assertStringNotContainsString('user_id', $body);
    }

    public function test_a_counselor_carries_a_badge(): void
    {
        $org = $this->org('Klinik Damai', AccessModel::Open);
        $counselor = $this->counselor('Dr Hana', [$org]);
        Sanctum::actingAs($counselor->user);
        $this->write('A tip for exam stress')->assertCreated()->assertJsonPath('post.author.counselor', true);

        $this->as('Aiman');
        $this->getJson('/api/v1/feed')->assertJsonPath('data.0.author.counselor', true);
    }

    public function test_the_feed_is_newest_first_and_pages(): void
    {
        config(['feed.per_page' => 2]);
        $a = $this->postId('one');
        $b = $this->postId('two');
        $c = $this->postId('three');

        $first = $this->getJson('/api/v1/feed')->assertOk();
        $this->assertSame([$c, $b], array_column($first->json('data'), 'id'));
        $this->assertSame($b, $first->json('next_cursor'));

        $second = $this->getJson('/api/v1/feed?cursor='.$b)->assertOk();
        $this->assertSame([$a], array_column($second->json('data'), 'id'));
        $this->assertNull($second->json('next_cursor'));
    }

    public function test_a_post_is_text_of_at_most_500_characters(): void
    {
        $this->write(str_repeat('a', 500))->assertCreated();
        $this->write(str_repeat('a', 501))->assertUnprocessable();
        $this->postJson('/api/v1/feed/posts', ['text' => '   '])->assertUnprocessable();
        $this->postJson('/api/v1/feed/posts', [])->assertUnprocessable();
    }

    public function test_the_same_client_id_stores_one_post(): void
    {
        $key = (string) Str::uuid();
        $first = $this->postJson('/api/v1/feed/posts', ['text' => 'Once', 'client_id' => $key])->assertCreated();
        $again = $this->postJson('/api/v1/feed/posts', ['text' => 'Once', 'client_id' => $key])->assertOk();

        $this->assertSame($first->json('post.id'), $again->json('post.id'));
        $this->assertSame(1, FeedPost::count());
    }

    public function test_words_about_ending_life_flag_the_post_but_still_save_it(): void
    {
        $this->write('I want to end my life')->assertCreated()
            ->assertJsonPath('crisis', true)
            ->assertJsonPath('post.text', 'I want to end my life')
            ->assertJsonStructure(['helplines' => [['key', 'name', 'number']]]);

        $this->assertSame(1, FeedPost::count());
    }

    public function test_there_is_a_daily_limit_by_malaysian_day(): void
    {
        config(['feed.daily_posts' => 2]);
        $this->write('a')->assertCreated();
        $this->write('b')->assertCreated();
        $this->write('c')->assertStatus(429)->assertJsonPath('code', 'daily_post_limit')->assertHeader('Retry-After');

        Carbon::setTestNow(Carbon::parse('2026-11-03 00:30:00', 'Asia/Kuala_Lumpur'));
        $this->write('d')->assertCreated();
    }

    public function test_only_the_author_edits_and_it_is_marked_edited(): void
    {
        $id = $this->postId('first words');
        $this->patchJson("/api/v1/feed/posts/{$id}", ['text' => 'better words'])->assertOk()
            ->assertJsonPath('post.text', 'better words')->assertJsonPath('post.edited', true);

        $this->as('Aiman');
        $this->patchJson("/api/v1/feed/posts/{$id}", ['text' => 'hijack'])->assertNotFound()->assertJsonPath('code', 'post_not_found');
        $this->assertSame('better words', FeedPost::first()->content);
    }

    public function test_only_the_author_deletes_and_a_deleted_post_is_gone_for_everyone(): void
    {
        $id = $this->postId();

        $this->as('Aiman');
        $this->deleteJson("/api/v1/feed/posts/{$id}")->assertNotFound();
        $this->getJson("/api/v1/feed/posts/{$id}")->assertOk();

        Sanctum::actingAs($this->me);
        $this->deleteJson("/api/v1/feed/posts/{$id}")->assertOk();
        $this->deleteJson("/api/v1/feed/posts/{$id}")->assertOk();
        $this->getJson("/api/v1/feed/posts/{$id}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/feed')->json('data'));
        $this->assertSame(1, FeedPost::count());
    }

    public function test_a_post_removed_by_a_moderator_is_gone_and_cannot_be_deleted_again_by_the_author(): void
    {
        $id = $this->postId();
        FeedPost::query()->update(['removed_at' => now()]);

        $this->getJson("/api/v1/feed/posts/{$id}")->assertNotFound();
        $this->deleteJson("/api/v1/feed/posts/{$id}")->assertNotFound();
    }

    public function test_a_like_is_one_per_person_and_can_be_taken_back(): void
    {
        $id = $this->postId();

        $aiman = $this->as('Aiman');
        $this->putJson("/api/v1/feed/posts/{$id}/reaction")->assertOk()->assertJsonPath('reactions', ['count' => 1, 'mine' => true]);
        $this->putJson("/api/v1/feed/posts/{$id}/reaction")->assertOk()->assertJsonPath('reactions.count', 1);
        $this->assertSame(1, $this->getJson('/api/v1/feed')->json('data.0.reactions.count'));
        $this->assertTrue($this->getJson('/api/v1/feed')->json('data.0.reactions.mine'));

        Sanctum::actingAs($this->me);
        $this->assertFalse($this->getJson("/api/v1/feed/posts/{$id}")->json('post.reactions.mine'));

        Sanctum::actingAs($aiman);
        $this->deleteJson("/api/v1/feed/posts/{$id}/reaction")->assertOk()->assertJsonPath('reactions', ['count' => 0, 'mine' => false]);
        $this->deleteJson("/api/v1/feed/posts/{$id}/reaction")->assertOk();
    }

    public function test_comments_are_oldest_first_and_counted(): void
    {
        $id = $this->postId();
        $this->as('Aiman');
        $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => 'first'])->assertCreated()->assertJsonPath('comment.author.name', 'Aiman');
        Carbon::setTestNow(now()->addMinute());
        $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => 'second'])->assertCreated();

        $list = $this->getJson("/api/v1/feed/posts/{$id}/comments")->assertOk();
        $this->assertSame(['first', 'second'], array_column($list->json('data'), 'text'));
        $this->assertSame(2, $this->getJson("/api/v1/feed/posts/{$id}")->json('post.comments_count'));
    }

    public function test_a_counselor_reply_carries_the_badge(): void
    {
        $id = $this->postId();
        $org = $this->org('Klinik Damai', AccessModel::Open);
        Sanctum::actingAs($this->counselor('Dr Hana', [$org])->user);

        $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => 'You are not alone'])->assertCreated()->assertJsonPath('comment.author.counselor', true);
    }

    public function test_a_comment_is_limited_flagged_idempotent_and_deletable_by_its_author(): void
    {
        $id = $this->postId();
        $this->as('Aiman');
        $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => str_repeat('a', 501)])->assertUnprocessable();

        $key = (string) Str::uuid();
        $one = $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => 'hi', 'client_id' => $key])->assertCreated();
        $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => 'hi', 'client_id' => $key])->assertOk();
        $this->assertSame(1, FeedComment::count());

        $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => 'I want to kill myself'])->assertCreated()->assertJsonPath('crisis', true);

        $cid = $one->json('comment.id');
        Sanctum::actingAs($this->me);
        $this->deleteJson("/api/v1/feed/comments/{$cid}")->assertNotFound();

        $author = FeedComment::query()->where('public_id', $cid)->first()->user;
        Sanctum::actingAs($author);
        $this->deleteJson("/api/v1/feed/comments/{$cid}")->assertOk();
        $this->assertSame(1, $this->getJson("/api/v1/feed/posts/{$id}")->json('post.comments_count'));
    }

    public function test_commenting_on_a_gone_post_is_not_found(): void
    {
        $id = $this->postId();
        $this->deleteJson("/api/v1/feed/posts/{$id}")->assertOk();

        $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => 'late'])->assertNotFound();
        $this->putJson("/api/v1/feed/posts/{$id}/reaction")->assertNotFound();
    }

    public function test_reporting_a_post_needs_a_reason_and_is_once_per_person(): void
    {
        $id = $this->postId();
        $this->as('Aiman');

        $this->postJson("/api/v1/feed/posts/{$id}/report", ['reason' => 'nonsense'])->assertUnprocessable();
        $this->postJson("/api/v1/feed/posts/{$id}/report", ['reason' => 'spam'])->assertStatus(202);
        $this->postJson("/api/v1/feed/posts/{$id}/report", ['reason' => 'harassment'])->assertStatus(202);

        $this->assertSame(1, FeedReport::count());
        $this->assertSame('spam', FeedReport::first()->reason);
        $this->assertSame('pending', FeedReport::first()->status);
    }

    public function test_reporting_a_comment_works_and_your_own_writing_cannot_be_reported(): void
    {
        $id = $this->postId();
        $this->postJson("/api/v1/feed/posts/{$id}/report", ['reason' => 'spam'])->assertStatus(409)->assertJsonPath('code', 'cannot_report_own');

        $this->as('Aiman');
        $cid = $this->postJson("/api/v1/feed/posts/{$id}/comments", ['text' => 'rude'])->json('comment.id');

        Sanctum::actingAs($this->me);
        $this->postJson("/api/v1/feed/comments/{$cid}/report", ['reason' => 'harassment'])->assertStatus(202);
        $this->assertSame(1, FeedReport::whereNotNull('feed_comment_id')->count());
    }

    public function test_muting_an_author_hides_their_posts_and_comments_only_for_you(): void
    {
        $theirs = $this->postId('mine to post');
        $aiman = $this->as('Aiman');
        $other = $this->postId('aiman post');
        $this->postJson("/api/v1/feed/posts/{$theirs}/comments", ['text' => 'aiman comment'])->assertCreated();

        Sanctum::actingAs($this->me);
        $mute = $this->postJson("/api/v1/feed/posts/{$other}/mute-author")->assertOk()->assertJsonPath('mute.name', 'Aiman')->json('mute.id');
        $this->postJson("/api/v1/feed/posts/{$other}/mute-author")->assertOk()->assertJsonPath('mute.id', $mute);

        $this->assertSame([$theirs], array_column($this->getJson('/api/v1/feed')->json('data'), 'id'));
        $this->assertSame([], $this->getJson("/api/v1/feed/posts/{$theirs}/comments")->json('data'));
        $this->assertSame([['id' => $mute, 'name' => 'Aiman']], $this->getJson('/api/v1/feed/mutes')->json('data'));

        Sanctum::actingAs($aiman);
        $this->assertCount(2, $this->getJson('/api/v1/feed')->json('data'));

        Sanctum::actingAs($this->me);
        $this->deleteJson("/api/v1/feed/mutes/{$mute}")->assertOk();
        $this->deleteJson("/api/v1/feed/mutes/{$mute}")->assertNotFound()->assertJsonPath('code', 'mute_not_found');
        $this->assertCount(2, $this->getJson('/api/v1/feed')->json('data'));
    }

    public function test_you_cannot_mute_yourself_or_remove_someone_elses_mute(): void
    {
        $id = $this->postId();
        $this->postJson("/api/v1/feed/posts/{$id}/mute-author")->assertStatus(409)->assertJsonPath('code', 'cannot_mute_self');

        $this->as('Aiman');
        $mute = $this->postJson("/api/v1/feed/posts/{$id}/mute-author")->json('mute.id');

        $this->as('Hana');
        $this->deleteJson("/api/v1/feed/mutes/{$mute}")->assertNotFound();
    }

    public function test_ids_that_are_not_public_ids_are_not_found(): void
    {
        $this->getJson('/api/v1/feed/posts/1')->assertNotFound();
        $this->getJson('/api/v1/feed/posts/'.str_repeat('z', 26))->assertNotFound()->assertJsonPath('code', 'post_not_found');
    }

    public function test_responses_are_private(): void
    {
        $this->getJson('/api/v1/feed')->assertHeader('Cache-Control', 'no-store, private');
    }
}
