<?php

namespace App\Console\Commands;

use App\Models\ChatSession;
use App\Services\Chat\ChatService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ends every conversation nobody wrote in for `chat.idle_end_minutes` and stores its dominant emotion (docs/CHAT.md). Safe to run again: an
 * ended conversation is never touched twice. Scheduled every fifteen minutes.
 */
class EndIdleChats extends Command
{
    protected $signature = 'chat:end-idle';

    protected $description = 'End chat conversations that have been quiet for a while, and store how they felt';

    public function handle(ChatService $chat): int
    {
        $ended = 0;

        ChatSession::query()->whereNull('ended_at')->where('last_message_at', '<=', now()->subMinutes((int) config('chat.idle_end_minutes')))
            ->orderBy('id')->chunkById(100, function ($sessions) use ($chat, &$ended) {
                foreach ($sessions as $session) {
                    try {
                        $chat->close($session, 'idle');
                        $ended++;
                    } catch (Throwable $e) {
                        report($e); // one conversation failing must not stop the rest
                    }
                }
            });

        $this->info("{$ended} conversation(s) ended.");

        return self::SUCCESS;
    }
}
