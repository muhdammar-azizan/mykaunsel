<?php

namespace App\Console\Commands;

use App\Facades\Audit;
use App\Models\ChatSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention for the chatbot (docs/CHAT.md): a conversation is deleted, with all its messages, `chat.retention_months` (12) after its last
 * message. One system audit entry with COUNTS only. --dry-run counts and changes nothing. Scheduled daily.
 */
class PurgeChats extends Command
{
    protected $signature = 'chat:purge {--dry-run : Count what would be deleted, change nothing}';

    protected $description = 'Delete chat conversations older than the retention period';

    public function handle(): int
    {
        $old = fn () => ChatSession::query()->where('last_message_at', '<', now()->subMonths((int) config('chat.retention_months')));

        if ($this->option('dry-run')) {
            $this->info("Would delete {$old()->count()} conversation(s).");

            return self::SUCCESS;
        }

        $sessions = 0;
        $messages = 0;

        Audit::asSystem(function () use ($old, &$sessions, &$messages) {
            $old()->orderBy('id')->chunkById(200, function ($chunk) use (&$sessions, &$messages) {
                $ids = $chunk->pluck('id')->all();

                DB::transaction(function () use ($ids, &$sessions, &$messages) {
                    $messageIds = DB::table('messages')->whereIn('chat_session_id', $ids)->pluck('id')->all();

                    DB::table('emotion_results')->whereIn('message_id', $messageIds)->delete();
                    $messages += DB::table('messages')->whereIn('chat_session_id', $ids)->delete();
                    $sessions += DB::table('chat_sessions')->whereIn('id', $ids)->delete();
                });
            });

            if ($sessions > 0) {
                Audit::log('system.chat_purged', null, ['sessions' => $sessions, 'messages' => $messages, 'retention_months' => (int) config('chat.retention_months')], 'system');
            }
        });

        $this->info("Deleted {$sessions} conversation(s) and {$messages} message(s).");

        return self::SUCCESS;
    }
}
