<?php

namespace Tests\Support;

use App\Services\Chat\AssistantModel;
use App\Services\Chat\AssistantUnavailable;
use Closure;

/** A stand-in for the language model: records every call, answers from a script, never touches the network. */
class FakeAssistant implements AssistantModel
{
    /** @var list<array{system: string, messages: list<array{role: string, content: string}>, maxTokens: int}> */
    public array $calls = [];

    /** @var Closure(string, array, int): string */
    public Closure $handler;

    public function __construct()
    {
        $this->handler = fn (string $system, array $messages, int $max) => str_contains($system, 'ONE feeling') ? 'sadness' : 'I hear you. That sounds hard.';
    }

    public function complete(string $system, array $messages, int $maxTokens): string
    {
        $this->calls[] = ['system' => $system, 'messages' => $messages, 'maxTokens' => $maxTokens];

        return ($this->handler)($system, $messages, $maxTokens);
    }

    /** Every call from now on fails like a model that is down. */
    public function down(): void
    {
        $this->handler = fn () => throw new AssistantUnavailable('down');
    }

    /** @return list<array{system: string, messages: array, maxTokens: int}> calls that were for a reply (not for naming a feeling) */
    public function replyCalls(): array
    {
        return array_values(array_filter($this->calls, fn ($c) => ! str_contains($c['system'], 'ONE feeling')));
    }
}
