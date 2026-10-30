<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * The chatbot (docs/CHAT.md). The person's consent to AI processing and every change of it; random public ids for sessions and messages (never a
 * counter in the app); the dominant emotion stored when a session ends; the last activity (to end idle sessions and to purge after the retention
 * period); whether the server's crisis check flagged a message; and a client-made id per message so a retried send never stores it twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When the CURRENT consent was given. Null = no consent (never given, or withdrawn).
            $table->dateTime('ai_consent_at')->nullable();
        });

        Schema::create('ai_consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('granted');
            $table->string('source', 16);
            $table->dateTime('created_at');

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
            $table->string('dominant_emotion', 24)->nullable();
            $table->string('ended_reason', 16)->nullable(); // person | idle
            $table->dateTime('last_message_at')->nullable();
            $table->index(['user_id', 'started_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
            $table->boolean('crisis')->default(false);
            $table->char('client_id', 36)->nullable();
            $table->unique(['chat_session_id', 'client_id']);
        });

        foreach (['chat_sessions', 'messages'] as $name) {
            DB::table($name)->whereNull('public_id')->orderBy('id')->select('id')->chunkById(500, function ($rows) use ($name) {
                foreach ($rows as $row) {
                    DB::table($name)->where('id', $row->id)->whereNull('public_id')->update(['public_id' => strtolower((string) Str::ulid())]);
                }
            });

            Schema::table($name, function (Blueprint $table) {
                $table->char('public_id', 26)->nullable(false)->change();
                $table->unique('public_id');
            });
        }

        DB::table('chat_sessions')->whereNull('last_message_at')->update(['last_message_at' => DB::raw('started_at')]);
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['chat_session_id', 'client_id']);
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'crisis', 'client_id']);
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'started_at']);
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'dominant_emotion', 'ended_reason', 'last_message_at']);
        });

        Schema::dropIfExists('ai_consent_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_consent_at');
        });
    }
};
