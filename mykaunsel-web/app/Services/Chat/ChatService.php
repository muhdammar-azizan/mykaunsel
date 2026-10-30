<?php

namespace App\Services\Chat;

use App\Exceptions\ChatRefused;
use App\Models\ChatSession;
use App\Models\Message;
use App\Models\User;
use App\Services\CounselorDirectory;
use App\Support\CounselorPublicPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The chatbot (docs/CHAT.md). The app calls Laravel and Laravel calls the model; the app never talks to the model.
 *
 * - Needs the person's AI consent (`users.ai_consent_at`); without it nothing is stored and nothing is sent.
 * - Crisis detection is done HERE by plain rules (CrisisDetector), before the model is asked, and the answer says so whatever the model does.
 *   A flag never blocks the conversation: the app shows a calm card with the helplines and the chat goes on.
 * - What goes to the model is only the words of the conversation, cleaned of e-mail addresses, links, identity-card and phone numbers
 *   (PromptScrubber). Never the person's name, e-mail or account.
 * - A message the model can't answer is still kept; the person is told in a friendly way and can ask for the answer again (`retryReply`),
 *   which never stores the person's message twice.
 * - A message carries a client-made id, so a send repeated after a lost answer stores nothing twice and returns what is already there.
 * - The model is never called while a database lock is held.
 */
class ChatService
{
    public function __construct(
        private readonly AssistantModel $model,
        private readonly CrisisDetector $crisis,
        private readonly PromptScrubber $scrubber,
        private readonly CounselorDirectory $directory,
    ) {}

    // =============================================================================================================
    //  Sending
    // =============================================================================================================

    /**
     * @return array<string, mixed>
     *
     * @throws ChatRefused
     */
    public function send(User $user, ?string $sessionPublicId, string $text, ?string $clientId): array
    {
        $this->requireConsent($user);

        $text = trim($text);

        if ($text === '') {
            throw new ChatRefused('message_empty');
        }

        $this->checkDailyLimit($user);

        [$session, $message] = DB::transaction(function () use ($user, $sessionPublicId, $text, $clientId) {
            $session = $this->lockOwnOrNew($user, $sessionPublicId);

            if ($clientId !== null) {
                $same = $session->messages()->where('client_id', $clientId)->first();

                if ($same !== null) {
                    return [$session, $same]; // a repeat: nothing is stored again
                }
            }

            if ($session->messages()->count() >= (int) config('chat.max_session_messages')) {
                throw new ChatRefused('session_full');
            }

            $message = $this->store($session, 'user', $text, $this->crisis->flags($text), $clientId);

            return [$session, $message];
        });

        return $this->answer($user, $session, $message);
    }

    /**
     * Asks for the answer to the person's last message again (the model could not answer before). If an answer exists it is returned as it is.
     *
     * @return array<string, mixed>
     *
     * @throws ChatRefused
     */
    public function retryReply(User $user, string $sessionPublicId): array
    {
        $this->requireConsent($user);

        $session = $this->own($user, $sessionPublicId);
        $last = $session->messages()->orderByDesc('id')->first();

        if ($last === null) {
            throw new ChatRefused('message_empty');
        }

        if ($session->ended_at !== null && $last->sender === 'user') {
            throw new ChatRefused('session_ended');
        }

        // Answering the last message of the person, whichever came after it is an answer already.
        $userMessage = $last->sender === 'user' ? $last : $session->messages()->where('sender', 'user')->orderByDesc('id')->first();

        if ($userMessage === null) {
            throw new ChatRefused('message_empty');
        }

        return $this->answer($user, $session, $userMessage);
    }

    // =============================================================================================================
    //  Ending, reading
    // =============================================================================================================

    /** Ends the conversation and stores its dominant emotion. Safe to repeat: an ended one answers as it is. */
    public function end(User $user, string $sessionPublicId, string $reason = 'person'): ChatSession
    {
        $session = $this->own($user, $sessionPublicId);

        return $this->close($session, $reason);
    }

    public function close(ChatSession $session, string $reason): ChatSession
    {
        $ended = DB::transaction(function () use ($session, $reason) {
            $locked = ChatSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->ended_at !== null) {
                return false;
            }

            $locked->update(['ended_at' => now(), 'ended_reason' => $reason === 'idle' ? 'idle' : 'person']);

            return true;
        });

        $session = $session->fresh();

        // The emotion comes after the conversation is closed and is allowed to fail: an ended conversation without one is complete.
        if ($ended && $session->dominant_emotion === null) {
            $emotion = $this->emotionOf($session);

            if ($emotion !== null) {
                $session->update(['dominant_emotion' => $emotion]);
            }
        }

        return $session->fresh();
    }

    /** @return array{data: list<array<string, mixed>>, page: int, last_page: int} */
    public function sessions(User $user, int $page): array
    {
        $paginator = ChatSession::query()->where('user_id', $user->getKey())->orderByDesc('started_at')->orderByDesc('id')->paginate(20, ['*'], 'page', max(1, $page));
        $ids = $paginator->getCollection()->pluck('id');

        $firsts = Message::query()->whereIn('chat_session_id', $ids)->where('sender', 'user')->orderBy('id')->get(['chat_session_id', 'message_text'])->unique('chat_session_id')->keyBy('chat_session_id');

        return [
            'data' => $paginator->getCollection()->map(fn (ChatSession $s) => $this->presentSession($s) + ['preview' => $this->preview((string) $firsts->get($s->id)?->message_text)])->values()->all(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function session(User $user, string $sessionPublicId): ?array
    {
        $session = ChatSession::query()->where('user_id', $user->getKey())->where('public_id', strtolower($sessionPublicId))->first();

        if ($session === null) {
            return null;
        }

        return $this->presentSession($session) + [
            'messages' => $session->messages()->orderBy('id')->limit((int) config('chat.max_session_messages') + 1)->get()->map(fn (Message $m) => $this->presentMessage($m))->all(),
        ];
    }

    /** @return list<array{key: string, name: string, number: string}> */
    public function helplines(): array
    {
        return array_values(array_map(fn (array $h) => ['key' => (string) $h['key'], 'name' => (string) $h['name'], 'number' => (string) $h['number']], (array) config('chat.helplines')));
    }

    // =============================================================================================================
    //  Inside
    // =============================================================================================================

    /** Makes (or finds) the answer to the person's message and builds the whole response. */
    private function answer(User $user, ChatSession $session, Message $userMessage): array
    {
        $reply = $session->messages()->where('sender', 'assistant')->where('id', '>', $userMessage->id)->orderBy('id')->first();
        $unavailable = false;

        if ($reply === null) {
            try {
                $text = $this->model->complete(ChatPrompt::system((bool) $userMessage->crisis), $this->history($session, $userMessage), (int) config('chat.reply_max_tokens'));
                $reply = $this->storeReply($session, $userMessage, $text);
            } catch (AssistantUnavailable $e) {
                // For the server's log only, and never with the conversation in it.
                Log::warning('Chat: the model did not answer. '.$e->getMessage());
                $unavailable = true;
            }
        }

        $crisis = (bool) $userMessage->crisis;

        return [
            'session' => $this->presentSession($session->fresh()),
            'message' => $this->presentMessage($userMessage->fresh()),
            'reply' => $reply === null ? null : $this->presentMessage($reply),
            'assistant_unavailable' => $unavailable,
            'crisis' => $crisis,
            'helplines' => $crisis ? $this->helplines() : [],
            'suggestion' => $crisis || $this->asksForCounselor((string) $userMessage->message_text) ? $this->suggestion($user) : null,
        ];
    }

    private function storeReply(ChatSession $session, Message $userMessage, string $text): Message
    {
        return DB::transaction(function () use ($session, $userMessage, $text) {
            ChatSession::query()->whereKey($session->getKey())->lockForUpdate()->first();

            // Another request may have answered meanwhile: one answer per message.
            $existing = $session->messages()->where('sender', 'assistant')->where('id', '>', $userMessage->id)->orderBy('id')->first();

            return $existing ?? $this->store($session, 'assistant', $text, false, null);
        });
    }

    /** The messages sent to the model: the most recent ones up to and including the person's, cleaned, starting with the person. */
    private function history(ChatSession $session, Message $upTo): array
    {
        $rows = $session->messages()->where('id', '<=', $upTo->id)->orderByDesc('id')->limit((int) config('chat.history_messages'))->get()->reverse()->values();

        while ($rows->isNotEmpty() && $rows->first()->sender !== 'user') {
            $rows->shift();
        }

        return $rows->map(fn (Message $m) => ['role' => $m->sender === 'user' ? 'user' : 'assistant', 'content' => $this->scrubber->scrub((string) $m->message_text)])->all();
    }

    private function store(ChatSession $session, string $sender, string $text, bool $crisis, ?string $clientId): Message
    {
        $message = Message::create([
            'chat_session_id' => $session->getKey(),
            'sender' => $sender,
            'message_text' => $text,
            'sent_at' => now(),
            'crisis' => $crisis,
            'client_id' => $clientId,
        ]);

        $session->update(['last_message_at' => $message->sent_at]);

        return $message;
    }

    private function lockOwnOrNew(User $user, ?string $sessionPublicId): ChatSession
    {
        if ($sessionPublicId === null) {
            return ChatSession::create(['user_id' => $user->getKey(), 'started_at' => now(), 'last_message_at' => now()]);
        }

        $found = ChatSession::query()->where('user_id', $user->getKey())->where('public_id', strtolower($sessionPublicId))->lockForUpdate()->first();

        if ($found === null) {
            throw new ChatRefused('session_not_found');
        }

        if ($found->ended_at !== null) {
            throw new ChatRefused('session_ended');
        }

        return $found;
    }

    private function own(User $user, string $sessionPublicId): ChatSession
    {
        return ChatSession::query()->where('user_id', $user->getKey())->where('public_id', strtolower($sessionPublicId))->first()
            ?? throw new ChatRefused('session_not_found');
    }

    private function requireConsent(User $user): void
    {
        if ($user->ai_consent_at === null) {
            throw new ChatRefused('ai_consent_required');
        }
    }

    private function checkDailyLimit(User $user): void
    {
        $tz = (string) config('counselors.directory.slot_timezone');
        $startOfDay = CarbonImmutable::now($tz)->startOfDay();

        $sent = Message::query()
            ->whereIn('chat_session_id', ChatSession::query()->where('user_id', $user->getKey())->select('id'))
            ->where('sender', 'user')->where('sent_at', '>=', $startOfDay->setTimezone((string) config('app.timezone')))->count();

        if ($sent >= (int) config('chat.daily_messages')) {
            throw new ChatRefused('chat_daily_limit', retryAfter: max(60, $startOfDay->addDay()->getTimestamp() - CarbonImmutable::now()->getTimestamp()));
        }
    }

    /** One word for what the person mostly felt, or null (no messages, the model could not say, or said something not on the list). */
    private function emotionOf(ChatSession $session): ?string
    {
        $mine = $session->messages()->where('sender', 'user')->orderBy('id')->limit(40)->pluck('message_text');

        if ($mine->isEmpty()) {
            return null;
        }

        $allowed = (array) config('chat.emotions');
        $words = $mine->map(fn ($t) => $this->scrubber->scrub((string) $t))->implode("\n---\n");

        try {
            $answer = $this->model->complete(ChatPrompt::emotion($allowed), [['role' => 'user', 'content' => $words]], 300);
        } catch (AssistantUnavailable $e) {
            Log::warning('Chat: the emotion of a conversation could not be named. '.$e->getMessage());

            return null;
        }

        $word = mb_strtolower((string) preg_replace('/[^a-z]/i', '', (string) strtok(trim($answer), " \n\t.,")));

        return in_array($word, $allowed, true) ? $word : null;
    }

    private function asksForCounselor(string $text): bool
    {
        $tidy = $this->crisis->tidy($text);

        return preg_match('/\b(counsel+or|counsel+ing|therapist|therapy|psycholog\w*|psychiatr\w*|kaunselor|kaunseling|terapi|pakar|professional (help|support)|talk to (a |an )?(person|human|professional)|bantuan profesional|jumpa (orang|pakar)|book (a )?(session|appointment|counsel\w*)|tempah)\b/u', $tidy) === 1;
    }

    /** Up to three counselors this person may book, by the same rules as search. @return array{counselors: list<array<string, mixed>>}|null */
    private function suggestion(User $user): ?array
    {
        $result = $this->directory->search($user, [], 1);

        $cards = $result['profiles']->take(3)
            ->map(fn ($profile) => CounselorPublicPresenter::card($profile, $result['slots']->get($profile->user_id, collect()), $result['orgIds']))
            ->values()->all();

        return $cards === [] ? null : ['counselors' => $cards];
    }

    private function preview(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 79).'…' : $text;
    }

    /** @return array<string, mixed> */
    public function presentSession(ChatSession $session): array
    {
        $tz = (string) config('counselors.directory.slot_timezone');

        return [
            'id' => $session->public_id,
            'started_at' => $session->started_at->setTimezone($tz)->toIso8601String(),
            'ended_at' => $session->ended_at?->setTimezone($tz)->toIso8601String(),
            'ended' => $session->ended_at !== null,
            'dominant_emotion' => $session->dominant_emotion,
        ];
    }

    /** @return array<string, mixed> */
    public function presentMessage(Message $message): array
    {
        return [
            'id' => $message->public_id,
            'sender' => $message->sender,
            'text' => (string) $message->message_text,
            'sent_at' => $message->sent_at->setTimezone((string) config('counselors.directory.slot_timezone'))->toIso8601String(),
            'crisis' => (bool) $message->crisis,
        ];
    }
}
