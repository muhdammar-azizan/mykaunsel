<?php

namespace App\Services\Chat;

/**
 * The one door to the language model. The chat only ever asks THIS, so the model, the vendor and the key live in one place and tests can
 * replace it with a fake that never touches the network.
 */
interface AssistantModel
{
    /**
     * One answer to a conversation.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages  oldest first, ending with the user's message
     *
     * @throws AssistantUnavailable the model can't answer now (no key, no network, an error, a refusal, an empty answer)
     */
    public function complete(string $system, array $messages, int $maxTokens): string;
}
