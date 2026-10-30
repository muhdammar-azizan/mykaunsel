<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ChatRefused;
use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The chatbot (docs/CHAT.md). The app calls this; this calls the model (never the other way, never from the app).
 *
 * POST /api/v1/chat/messages                 send a message (a new conversation when `session` is left out); the answer comes back in the same call
 * POST /api/v1/chat/sessions/{id}/reply      ask again for the answer to the last message (the model could not answer before)
 * POST /api/v1/chat/sessions/{id}/end        end the conversation (stores its dominant emotion); safe to repeat
 * GET  /api/v1/chat/sessions, /sessions/{id} the person's own history, read only
 * GET  /api/v1/chat/helplines                where to get help, always reachable
 *
 * Another person's conversation and an unknown one are the same `404 session_not_found`.
 */
class ChatController extends Controller
{
    public function send(Request $request, ChatService $chat): JsonResponse
    {
        $input = $request->validate([
            'text' => ['required', 'string', 'max:'.(int) config('chat.max_message_chars')],
            'session' => ['nullable', 'string', 'max:26'],
            'client_id' => ['nullable', 'uuid'],
        ]);

        try {
            return $this->private(response()->json($chat->send($request->user(), $input['session'] ?? null, $input['text'], $input['client_id'] ?? null)));
        } catch (ChatRefused $refused) {
            return $refused->toResponse();
        }
    }

    public function reply(Request $request, string $publicId, ChatService $chat): JsonResponse
    {
        try {
            return $this->private(response()->json($chat->retryReply($request->user(), $publicId)));
        } catch (ChatRefused $refused) {
            return $refused->toResponse();
        }
    }

    public function end(Request $request, string $publicId, ChatService $chat): JsonResponse
    {
        try {
            return $this->private(response()->json(['session' => $chat->presentSession($chat->end($request->user(), $publicId))]));
        } catch (ChatRefused $refused) {
            return $refused->toResponse();
        }
    }

    public function index(Request $request, ChatService $chat): JsonResponse
    {
        $query = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:10000']]);

        return $this->private(response()->json($chat->sessions($request->user(), (int) ($query['page'] ?? 1))));
    }

    public function show(Request $request, string $publicId, ChatService $chat): JsonResponse
    {
        $found = $chat->session($request->user(), $publicId);

        return $found === null ? (new ChatRefused('session_not_found'))->toResponse() : $this->private(response()->json(['session' => $found]));
    }

    /** No consent needed: help is never behind a setting. */
    public function helplines(ChatService $chat): JsonResponse
    {
        return response()->json(['helplines' => $chat->helplines()])->header('Cache-Control', 'public, max-age=300');
    }

    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
