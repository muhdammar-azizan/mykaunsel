<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * The wellness Feed (docs/FEED.md). Posts get a random public id (never a counter in the app), a client-made id so a retried post is stored once,
 * and three "gone" marks: `edited_at`, `deleted_at` (the author took it down) and `removed_at` (a moderator did, later). Comments, reports (to the
 * Moderate Feed queue) and mutes (one person hiding another's writing from their own feed) are new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
            $table->char('client_id', 36)->nullable();
            $table->dateTime('edited_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('removed_at')->nullable();
            $table->index('created_at');
            $table->unique(['user_id', 'client_id']);
        });

        DB::table('feed_posts')->whereNull('public_id')->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('feed_posts')->where('id', $row->id)->whereNull('public_id')->update(['public_id' => strtolower((string) Str::ulid())]);
            }
        });

        Schema::table('feed_posts', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable(false)->change();
            $table->unique('public_id');
        });

        Schema::create('feed_comments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('feed_post_id')->constrained('feed_posts');
            $table->foreignId('user_id')->constrained('users');
            $table->text('content');
            $table->char('client_id', 36)->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('removed_at')->nullable();
            $table->timestamps();

            $table->index(['feed_post_id', 'id']);
            $table->unique(['user_id', 'client_id']);
        });

        Schema::create('feed_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('feed_post_id')->nullable()->constrained('feed_posts');
            $table->foreignId('feed_comment_id')->nullable()->constrained('feed_comments');
            $table->string('reason', 24);
            $table->string('status', 16)->default('pending'); // pending | dismissed | actioned (the Moderate Feed page decides)
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'feed_post_id']);
            $table->unique(['user_id', 'feed_comment_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('feed_mutes', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('muted_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('created_at');

            $table->unique(['user_id', 'muted_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_mutes');
        Schema::dropIfExists('feed_reports');
        Schema::dropIfExists('feed_comments');

        Schema::table('feed_posts', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropUnique(['user_id', 'client_id']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['public_id', 'client_id', 'edited_at', 'deleted_at', 'removed_at']);
        });
    }
};
