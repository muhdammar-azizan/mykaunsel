# The chatbot (server side)

The only AI part of MyKaunsel. **The app calls Laravel and Laravel calls the model; the app never talks to the model.** The Anthropic API key lives only in the server's `.env` (`ANTHROPIC_API_KEY`), is read through `config('services.anthropic.key')`, and is never in code, in the app, in git or in a page (`AnthropicKeyConfigTest` guards this). With no key the feature is simply off: the person gets the friendly "can't answer now" message.

Code: `App\Services\Chat\*` (`ChatService`, `CrisisDetector`, `PromptScrubber`, `ChatPrompt`, `AssistantModel` / `AnthropicAssistantModel`), `Api\V1\ChatController`, `chat:end-idle`, `chat:purge`, `config/chat.php`. Tests: `ChatApiTest`, `ChatCommandsTest`, `CrisisDetectorTest`, `PromptScrubberTest` (the model is always a fake in tests: no test touches the network). The Flutter screens for this are a later step.

## The model

`services.anthropic.model` (env `ANTHROPIC_CHAT_MODEL`, default `claude-opus-5-5`), called through Anthropic's official PHP SDK (`anthropic-ai/sdk`, added to `composer.json`). Effort `low` (`CHAT_EFFORT`) so replies are quick (about 4 seconds in a first real check) and cheap; replies are short (the prompt asks for two to five sentences). `CHAT_REPLY_MAX_TOKENS` (2000) is the ceiling. To use a cheaper or faster model, set `ANTHROPIC_CHAT_MODEL`; nothing else changes. Every failure (no key, no network, an error, a refusal, an empty answer) becomes one `AssistantUnavailable`, which the chat turns into a friendly message; only its class and HTTP status go to the log, never the conversation.

## What the model is told, and what it is sent

- **A companion, not a counselor.** The system prompt (`ChatPrompt`) asks for short, warm replies in the person's language (English, Malay or a mix), listening first, saying it is an AI, no diagnosis or medication advice, an occasional gentle mention that a real counselor on MyKaunsel can help, no requests for personal details, and a calm safety response when someone talks about ending their life (care first, helplines, emergency department if in immediate danger, never methods).
- **Only the words of the conversation** are sent, the latest `chat.history_messages` (20), starting with the person's. Never the person's name, e-mail or account. E-mail addresses, web links, Malaysian identity-card numbers and phone numbers are replaced (`[email]`, `[link]`, `[id number]`, `[phone]`) in the copy that is sent; the conversation is stored as written. A name typed inside a sentence is not found: this is a safety net, not a promise.

## Consent

The chat needs the person's AI-processing consent: `users.ai_consent_at` (null = none). Without it `403 ai_consent_required` and nothing is stored or sent. `PUT /account/ai-consent` `{consent: bool}` gives or withdraws it; every real change is recorded in `ai_consent_events` (who, granted or withdrawn, when); asking for the state already held records nothing. The user object (`/auth/me`, sign-in) carries `ai_consent`. Withdrawing turns the chat off at once; the history stays readable. **Not done yet:** the tick at registration and the toggle in the app's settings (app steps), and existing accounts have no consent until they give it.

## Crisis detection (on the server, not the model)

`CrisisDetector` is a set of plain rules over the tidied message (case, accents, apostrophes and punctuation folded), in English and Bahasa Malaysia, with texting shortcuts (`nk mati`, `xnak hidup`). It runs **before** the model is asked and whatever the model answers or fails to answer. It prefers a false alarm to a miss. A flagged message is stored with `crisis = true`; the response carries `crisis: true` and the `helplines`; the model's system prompt gets a note for that turn. **A flag never blocks the conversation.** `GET /chat/helplines` needs no sign-in and no consent: help is never behind a setting.

**Helplines are in `config/chat.php`: HEAL 15555 and Talian Kasih 15999 (from the owner's list). They must be confirmed before launch, and Befrienders is left out until its number is confirmed (open decision in APP_SCREENS.md).** The crisis rules also need review by someone who knows the language young Malaysians use before launch.

## API (all under `/api/v1/chat`, token and confirmed e-mail)

- `POST /messages` `{text, session?, client_id?}`: sends a message (a new conversation when `session` is left out) and returns the answer **in the same call**: `{session, message, reply|null, assistant_unavailable, crisis, helplines, suggestion|null}`. `text` is at most `chat.max_message_chars` (1000). `client_id` (a UUID the app makes per message) makes a repeat after a lost answer store nothing twice and return what is already there.
- `POST /sessions/{id}/reply`: asks again for the answer to the last message when the model could not answer (the person's message is kept; it is never stored twice; an answer that exists is returned as it is).
- `POST /sessions/{id}/end`: ends the conversation and stores its **dominant emotion** (one word from `chat.emotions`, named by the model from what the person wrote; empty if the model can't or says something not on the list; the conversation ends either way). Safe to repeat.
- `GET /sessions` (paged, newest first, each with a short `preview`) and `GET /sessions/{id}` (whole conversation, oldest first): read only, no delete (as specified).
- `suggestion`: up to three counselors the person may book (the same rules and card shape as search), offered when the message was flagged or asks for a counselor (`counselor`, `kaunselor`, `therapist`, `jumpa pakar`, …); null otherwise or when there is none.
- Refusals: `403 ai_consent_required`, `404 session_not_found` (also for another person's), `409 session_ended`, `409 session_full` (`chat.max_session_messages`, 200), `422` for an empty or too long message, `429 chat_daily_limit` (200 messages a day, with `Retry-After` until Malaysian midnight), and the per-minute and hourly limit `api-chat` (10 and 60).
- The model is **never called while a database lock is held** (tested): the message is stored, the model is asked, the answer is stored.

## Ending, retention

- `chat:end-idle` (every 15 minutes): ends conversations quiet for `chat.idle_end_minutes` (60) with the emotion, `ended_reason = idle`. Safe to run again.
- `chat:purge` (daily 03:40): deletes conversations whose last message is older than `chat.retention_months` (12), with their messages and emotion results. One system audit entry (`system.chat_purged`) with **counts only**. `--dry-run` changes nothing.
- A queue worker is not needed for the chat itself. The scheduler is needed for both commands.

## Not built

The app screens (Chat, history, crisis card, counselor card, consent toggle and the registration tick), a way for the owner to see that the key works other than trying the chat, per-account spend limits (the daily message cap is the only brake), and streaming of the reply (the app waits for the whole answer, a few seconds).
