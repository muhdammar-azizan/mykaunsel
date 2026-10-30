<?php

namespace App\Services\Chat;

use Anthropic\Client;
use Throwable;

/**
 * The language model, through Anthropic's official PHP SDK. The key is read from config('services.anthropic.key') (the server's .env) and
 * nowhere else; with no key the feature is simply off (AssistantUnavailable). Whatever goes wrong, the caller only ever sees AssistantUnavailable:
 * the person gets a friendly message and the cause goes to the log (without the conversation).
 */
class AnthropicAssistantModel implements AssistantModel
{
    private ?Client $client = null;

    public function complete(string $system, array $messages, int $maxTokens): string
    {
        $key = (string) config('services.anthropic.key');

        if ($key === '') {
            throw new AssistantUnavailable('No Anthropic API key is set.');
        }

        try {
            $this->client ??= new Client(apiKey: $key);

            $message = $this->client->messages->create(
                model: (string) config('services.anthropic.model'),
                maxTokens: $maxTokens,
                system: $system,
                messages: $messages,
                outputConfig: ['effort' => (string) config('chat.effort')],
            );
        } catch (Throwable $e) {
            // The exception's text can quote the request: log only its class and status, never the conversation.
            throw new AssistantUnavailable('The model call failed: '.$e::class.($e instanceof \Anthropic\Core\Exceptions\APIStatusException ? ' (HTTP '.$e->status.')' : ''), 0, $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new AssistantUnavailable('The model declined to answer.');
        }

        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $text = trim($text);

        if ($text === '') {
            throw new AssistantUnavailable('The model answered with no text.');
        }

        return $text;
    }
}
