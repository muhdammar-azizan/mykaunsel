<?php

/*
 * The chatbot (docs/CHAT.md). The Anthropic API key and the model are in config/services.php; everything the chat itself decides is here.
 */
return [

    // The most characters one message may have, and how many earlier messages are sent to the model with a new one.
    'max_message_chars' => (int) env('CHAT_MAX_MESSAGE_CHARS', 1000),
    'history_messages' => (int) env('CHAT_HISTORY_MESSAGES', 20),

    // A session holds at most this many messages (the app then starts a new one).
    'max_session_messages' => (int) env('CHAT_MAX_SESSION_MESSAGES', 200),

    // Limits per person: a burst, an hour, and a day (messages the person sent).
    'rate_per_minute' => (int) env('CHAT_RATE_PER_MINUTE', 10),
    'rate_per_hour' => (int) env('CHAT_RATE_PER_HOUR', 60),
    'daily_messages' => (int) env('CHAT_DAILY_MESSAGES', 200),

    // A session nobody wrote in for this many minutes is ended by `chat:end-idle`.
    'idle_end_minutes' => (int) env('CHAT_IDLE_END_MINUTES', 60),

    // Sessions and messages are deleted this many months after the last message (`chat:purge`).
    'retention_months' => (int) env('CHAT_RETENTION_MONTHS', 12),

    // How the reply is made: the length ceiling and the effort (the model decides how much to think).
    'reply_max_tokens' => (int) env('CHAT_REPLY_MAX_TOKENS', 2000),
    'effort' => env('CHAT_EFFORT', 'low'),

    // The feelings a finished session can be summed up as (one word). Anything else the model says is stored as nothing.
    'emotions' => ['sadness', 'anxiety', 'stress', 'anger', 'loneliness', 'fear', 'guilt', 'hope', 'calm', 'joy', 'neutral'],

    // Where to turn in a crisis. ***CONFIRM EVERY NUMBER WITH THE OWNER BEFORE LAUNCH*** (an open decision in docs/APP_SCREENS.md). A line
    // without a number is shown by name only. Befrienders is left out until its number is confirmed.
    'helplines' => [
        ['key' => 'heal', 'name' => 'HEAL', 'number' => '15555'],
        ['key' => 'talian_kasih', 'name' => 'Talian Kasih', 'number' => '15999'],
    ],
];
