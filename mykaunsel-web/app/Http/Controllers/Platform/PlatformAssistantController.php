<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Chat\AssistantUnavailable;
use App\Services\Platform\PlatformAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform Admin → the "Ask anything" box on the dashboard. One question in, one short answer (and up to three page links) out. Nothing is stored.
 * The model only ever sees counts (see PlatformAssistant); the key stays on the server.
 */
class PlatformAssistantController extends Controller
{
    public function ask(Request $request, PlatformAssistant $assistant): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);

        try {
            return response()->json($assistant->ask($request->user(), trim($data['question'])));
        } catch (AssistantUnavailable $e) {
            report($e);

            return response()->json(['message' => 'The assistant can not answer right now. Please try again in a moment.'], 503);
        }
    }
}
