# API for the mobile app (Phase 1: accounts)

Laravel Sanctum bearer tokens. Base path `/api/v1`. Everything answers in JSON (even errors: 401, 403, 422, 429, 503), whatever `Accept` header is sent. Only individuals' accounts and the shared sign-in are covered so far; bookings, chat, feed and the counselor tools are later phases.

## Endpoints

| Method | Path | Needs | What it does |
|---|---|---|---|
| GET | `/status` | nothing | Is MyKaunsel in maintenance (`maintenance.on` + `message` + `reopens_at`, when it is expected to end), which sign-ups are open (`registration.organization / counselor / individual` → `open`, `message`), and must this app version update (`app.update_required`, `app.min_version`, `app.message`, `app.store_url`). **Call this first.** It is the only route that still answers during maintenance or to an outdated app. |
| POST | `/auth/register` | open sign-up | `name`, `email` (lowercase), `password`, `password_confirmation`, `ai_consent` (must be true: the agreement to AI processing, recorded; see [CHAT.md](CHAT.md)) → 201. **No token yet**: a confirmation email is sent. |
| POST | `/auth/signup-link` | open | `type` (`organization` or `counselor`) and `email`: e-mails the public web sign-up address for that kind. Answers `202` with the same words for every address (and silently sends nothing after 3 an hour to one address); `403` with the admin's words when that kind is closed; `429` by the address limit. |
| POST | `/auth/login` | – | `email`, `password`, `device_name` → 200 `{token, token_type: "Bearer", user}`. |
| POST | `/auth/two-factor` | – | For accounts with two-factor on: `challenge`, `code` (or `recovery_code`), `device_name` → 200 like login. |
| POST | `/auth/email/resend` | – | `email` → 202, the same answer for every address. |
| POST | `/auth/forgot-password` | – | `email` → 202, the same answer for every address. The reset page itself is on the web. |
| GET | `/auth/me` | token | The signed-in person. |
| POST | `/auth/logout` | token | Signs out this device. |
| POST | `/auth/logout-all` | token | Signs out every device. |
| PATCH | `/account/profile` | token | `name` (2–100), `timezone` (UTC or an Asia zone) → `{user}`. The email is **not** changeable here (it needs its own confirmation step; do it on the web). Only ever the signed-in person: no id is accepted. |
| PUT | `/account/password` | token | `current_password`, `password`, `password_confirmation` (same strength rules as everywhere, and different from the current one). Signs out **every other** phone and web session; this phone stays signed in. Audited (`account.password_changed`). |
| GET | `/account/devices` | token | The phones signed in to this account (`id`, `name`, `current`, `last_used_at`, `created_at`), this one first. Never the token, never anyone else's. |
| POST | `/account/devices/{id}/revoke` | token | Signs ANOTHER phone out; needs `current_password`. This phone → 422 `current_device` (use `/auth/logout`). Someone else's or unknown → 404. |
| GET | `/counselors` | token | Search counselors: `q` (name, 2+ letters), `specialization`, `language`, `mode` (`online`/`physical`), `state`, `page`. 20 per page, `{data: [card], meta: {page, per_page, total, last_page}}`. Soonest free slot first, then by name. |
| GET | `/counselors/filters` | token | The choices for the filter screen (`specializations`, `languages`, `session_modes`, `states`), from what this person can really find. |
| GET | `/counselors/{id}` | token | One counselor (the card plus `accepts_requests` and `upcoming_slots`). `id` is the random public id from the list. Hidden and non-existent are both `404`. |
| GET | `/counselors/{id}/availability` | token | The times of one counselor that can be picked: `from`, `days` (1–14), `mode` (`online`/`physical`). Read only. See [AVAILABILITY.md](AVAILABILITY.md). `404` + `code: counselor_unavailable` for every reason the counselor cannot be booked. |
| GET | `/bookings/preview?slot=` | token | Read-only check for the confirm screen: `valid`, `reason` and the details (time, mode, place, cancellation deadline, limits). Claims nothing. See [AVAILABILITY.md](AVAILABILITY.md#the-confirm-step-built). |
| POST | `/bookings` | token | **Books a Mode A slot.** Header `Idempotency-Key` (UUID); body `slot`, `acknowledged_policy: true`, `expected_starts_at`, `expected_duration_minutes`, `expected_mode`. `201` with the booking; `404 counselor_unavailable`, `409 slot_taken` / `slot_changed` / `time_conflict` / `upcoming_limit_reached` / `request_in_progress`, `422` (also `idempotency_key_reuse`), `429` with `Retry-After`. |
| GET | `/bookings?scope=upcoming\|past&page=` | token | The person's OWN bookings. Upcoming: confirmed and not ended, soonest first. Past: the rest, newest first, 20 a page. See [AVAILABILITY.md](AVAILABILITY.md#my-bookings-read-only). |
| GET | `/bookings/{id}` | token | One of them, with the meeting link (only inside its window, only when added) or the full address. Someone else's or unknown: `404 booking_not_found`. |
| POST | `/bookings/{id}/cancel` | token | Cancels the person's own booking before its deadline and gives the time back, in one transaction. Safe to repeat. `409 cancellation_closed` / `not_cancellable`, `404 booking_not_found`. See [AVAILABILITY.md](AVAILABILITY.md#cancelling). |
| POST | `/bookings/{id}/reschedule` | token | Moves the person's own booking to another time of the SAME counselor (body: `slot`, `expected_starts_at`, `expected_duration_minutes`, `expected_mode`), under the same time limit as cancelling, at most `max_reschedules` times; claims the new time and gives the old one back in one transaction. Safe to repeat. `409 reschedule_closed` / `reschedule_limit_reached` / `not_reschedulable` / `slot_taken` / `slot_changed` / `time_conflict`, `422 different_counselor`, `404 booking_not_found` / `counselor_unavailable`. See [AVAILABILITY.md](AVAILABILITY.md#rescheduling). |
| GET | `/counselors/{id}/working-hours` | token | The counselor's weekly hours the person may see, to pick a request time from. See [AVAILABILITY.md](AVAILABILITY.md#time-requests-mode-b). |
| POST | `/counselors/{id}/requests` | token | Ask for a time inside the weekly hours (`date`, `time`, optional `message`). `201`, or `200` with the same request when repeated while it waits. `422 request_time_invalid`, `409 requests_closed` / `slot_taken` / `time_conflict` / `request_limit_reached`. |
| GET | `/requests`, `/requests/{id}` | token | The person's own time requests (`pending`, `approved`, `rejected`, `expired`, `withdrawn`). |
| POST | `/requests/{id}/withdraw` | token | Withdraw a request that still waits. Safe to repeat. `409 request_not_pending`. |
| PUT | `/account/ai-consent` | token | Gives or withdraws the consent to AI processing the chatbot needs (`consent`). Recorded; the user object carries `ai_consent`. |
| GET, PUT | `/account/notification-preferences` | token | The e-mails the person can switch off (`booking_emails`: confirmations, answers to time requests, changes). PUT `{key, enabled}` saves one choice and answers the list as it now is. E-mail only; essential notices (a session cancelled for them by the platform) are always sent. |
| GET | `/account/organization` | token | The organization the person belongs to or waits to join, and the invitations waiting for their CONFIRMED e-mail address: `{organization: null\|{name, status: "waiting"\|"member", grace_days}, invitations: [{id, organization}]}`. A client has only one organization (the server enforces it). |
| POST | `/account/organization/join` | token | `{code}`: asks to join with a reference code (capitals, spaces and dashes do not matter). 201 with the organization, status `waiting` until its HR approves. Refusals carry `code`: `invalid_code` (422, the same for a wrong, expired, switched-off or used-up code), `waiting_approval`, `already_member`, `in_other_organization` (409, the message names the organization). 5 tries a minute. |
| DELETE | `/account/organization` | token | Leaves the organization (or withdraws a waiting request). Answers `{left: {organization, cancelled, kept}}`: later sessions with its counselors are cancelled except those within `grace_days`; the person, the counselors and the organization's admins (name only) are e-mailed. 404 `not_a_member` when there is none. |
| DELETE | `/account` | token | Deletes the account (docs/ACCOUNT_DELETION.md). `current_password` and `confirm: "DELETE"` are required (422 with the field named otherwise). Answers `{deleted: true, cancelled: n}`; every sign-in is gone afterwards. Counselors and administrators: `403 staff_account`. |
| POST | `/account/organization/invitations/{id}/accept`, `/decline` | token | Answers an invitation sent by the organization's admin to the person's confirmed e-mail address. Accepting makes them an employee at once (and lifts a waiting request of the same organization); the one-organization rule applies (409 `in_other_organization`). A cancelled, expired, someone else's or unknown invitation is 404 `invitation_not_found`. |
| GET | `/status` | none | (also) `support_email`: where people write for help and a copy of their data, or null when `SUPPORT_EMAIL` is not set on the server. |
| POST | `/chat/messages` | token | Sends a chat message (`text`, optional `session`, `client_id`) and returns the model's answer in the same call, with `crisis`, `helplines` and an optional counselor `suggestion`. `403 ai_consent_required`. See [CHAT.md](CHAT.md). |
| POST | `/chat/sessions/{id}/reply` | token | Asks again for the answer to the last message (when the model could not answer). |
| POST | `/chat/sessions/{id}/end` | token | Ends the conversation and stores its dominant emotion. Safe to repeat. |
| GET | `/chat/sessions`, `/chat/sessions/{id}` | token | The person's own chat history, read only. |
| GET | `/chat/helplines` | none | Where to get help in a crisis; needs no sign-in and no consent. |
| GET | `/feed`, `/feed/posts/{id}`, `/feed/posts/{id}/comments`, `/feed/mutes` | token | The Wellness Feed: posts under the author's name, newest first. See [FEED.md](FEED.md). |
| POST, PATCH, DELETE | `/feed/posts…`, `/feed/comments/{id}`, `/feed/mutes/{id}` | token | Write, edit, delete, comment, report (`202`), mute. A post that may signal danger answers `crisis` + `helplines` and is still saved. |
| PUT, DELETE | `/feed/posts/{id}/reaction` | token | Like / take back; safe to repeat. |

Send the token as `Authorization: Bearer <token>`. Tokens start with `<id>|mkp_` and last 30 days (`SANCTUM_EXPIRATION_MINUTES`); each device name gets its own token, at most 10 per person (the oldest are removed).

## Answers the app must handle

- **422** validation (`errors.<field>`), including wrong email/password (`errors.email`, the same text for a wrong password and an unknown email) and wrong/expired two-factor codes.
- **202 + `code: "two_factor_required"` + `challenge`** from `/auth/login`: ask for the 6-digit code, then call `/auth/two-factor` (the challenge works for 10 minutes; after 5 wrong codes sign in again).
- **403 + `code: "email_not_verified"`** from `/auth/login`, **and from every signed-in route** if the address is not confirmed: tell the person to confirm their email (offer `/auth/email/resend`). A token is never given before the address is confirmed.
- **403** from `/auth/register`: individual sign-up is closed; `message` is the text the Platform Admin wrote.
- **503** on every route except `/status`: maintenance mode; `message` is the Platform Admin's text, and `reopens_at` (ISO-8601, UTC) says when it is expected to end, or is `null` if no time was set or it has passed. `Retry-After` (seconds) is sent: the time left until `reopens_at`, or 600 without one. `/status` carries the same time as `maintenance.reopens_at`.
- **426 + `code: "app_update_required"`** (`min_version`, `store_url`) on every route except `/status`: this app version is older than `API_MIN_APP_VERSION`. Show an update screen with the store link.
- **429** (with `Retry-After`): too many requests, show "try again in a moment". **401**: missing / expired / removed token (sign in again).

## Headers the app must send on every request

- `X-App-Version: 1.2.3` (the app's own version, `major.minor.patch`) and `X-App-Platform: android|ios`. Without the version the app can never be forced to update, so it has to be in the **first release**.
- `Accept: application/json` and `Authorization: Bearer <token>` when signed in.

## Production notes

- **Shared mobile IPs.** Many phones on one mobile network share one public IP, so the tight rate limits follow the *email* (login 10/min, email-sending 3 per 10 minutes), the *two-factor challenge* (10/min) or the *signed-in person* (120/min). The per-IP limits (hundreds per minute) are only a ceiling against floods. They live in `AppServiceProvider::apiRateLimits`.
- **Behind a proxy / load balancer** set `TRUSTED_PROXIES` in `.env`, otherwise every visitor shows up with the proxy's IP (all limits and the audit log's IP would be wrong).
- **Forcing an update:** raise `API_MIN_APP_VERSION` in `.env` only when an API change would break older apps, and keep the store links filled in. A request without `X-App-Version` is never blocked (browsers, curl).

## How it ties to the web

- **Registration Control** applies: the API register route uses the same `individual` switch as the web, and the maintenance switch blocks the API too (a Platform Admin is not exempt here: admins work in the web).
- **The confirmation email** for app sign-ups links to `/email/verify-app/{id}/{hash}` (signed, expires): it works in any browser without a web sign-in and just says to go back to the app.
- **Two-factor** is the same as the web: same secret, same recovery codes, same attempt limit, same audit entries. A password alone never gives a token to an account with two-factor.
- **Password reset** uses the web page; completing it signs the person out of the app on every device.
- **Audit log**: app sign-ins and failures are recorded like web ones (`auth.login`, `auth.login_failed`, `account.two_factor_failed`, `account.recovery_code_used`).
- An individual is an account with **no organization membership** (as on the web). People who also work as counselors or admins can sign in to the app too; what they may do will be decided per endpoint in later phases.

## Account

`current_password` is checked on every call that proves who is holding the phone (new password, signing a phone out). Those two routes share one limit, **5 tries a minute and 20 an hour per person** (`api-sensitive`), so a stolen token can not be used to guess the password. Wrong password = 422 on `current_password`; over the limit = 429.

Changing the password also ends the person's **web sessions**: the web compares the password stored in the session with the account's on every request.

## Counselor search

What is shown, to whom, and what never is: [`PUBLIC_COUNSELOR_DATA.md`](PUBLIC_COUNSELOR_DATA.md). Slot times are Malaysian time with the offset (`2026-11-03T10:00:00+08:00`). Sample data for development: `php artisan db:seed --class=DirectorySampleSeeder` (after the normal seeders; safe to run twice).

## Rules for every new endpoint

1. Put it in `routes/api.php` inside the `auth:sanctum` + `verified` group unless it must be public.
2. It is automatically JSON and blocked during maintenance. Only add a route to `maintenance_allowed_routes` (`config/platform-settings.php`) if the app truly needs it while MyKaunsel is down.
3. If it creates a new kind of account, put `registration.open:<role>` on it.
4. Rate-limit anything that accepts credentials, codes or sends email, with a named limiter from `apiRateLimits` (never a plain per-IP number: shared mobile IPs).
5. Authorize the data, not just the person (a token proves who they are, not what they may open).
6. Never return `password`, two-factor fields or anything from `$hidden`; build the answer by hand (see `ApiAuthService::present`).

## Not yet / open

- Sign-in with a phone number is planned later (the owner will decide when).
- No token abilities/scopes yet (every token is full access). Revisit when counselor-only endpoints exist.
- Push notifications, deep links from the confirmation email into the app, and account deletion are not built.
