# Availability: the times of a counselor that can be picked

`GET /api/v1/counselors/{id}/availability` (token, confirmed email). It is what the app's **Pick a slot (Mode A)** screen reads. **It only reads.** It never creates a booking and never holds a time: the booking is made later, on the confirm screen, and a slot is claimed there in one atomic step.

Mode A means the counselor opened specific slots and the person picks one. Mode B (request a time) is a different feature and is not built.

## Request

| Parameter | Meaning |
|---|---|
| `{id}` | The counselor's random **public id** (the one the list and the profile already give). Never a row id. |
| `from` | `YYYY-MM-DD`, in Malaysian time. Default: today. An earlier date is moved to today. |
| `days` | 1 to 14, default 14 (`booking.availability_days_per_request`). The range is cut at the booking horizon. |
| `mode` | `online` or `physical` (optional filter). Note **`physical`**, the same word as the counselor search and the app use; the screen says "In-person". |

Anything else in the address is ignored (there is no `context_org_id`: the server decides which contexts a person sees, never the app). Bad input is a `422`.

## Answer

All times are ISO 8601 with the `+08:00` offset, made from the zone name `Asia/Kuala_Lumpur`.

```json
{
  "counselor": { "id": "01k…", "display_name": "Aina Damai", "avatar_url": null, "verified": true, "modes_offered": ["online", "physical"], "accepts_requests": true },
  "time_zone": "Asia/Kuala_Lumpur",
  "booking_window": { "earliest_start": "2026-11-02T11:00:00+08:00", "last_date": "2026-12-02", "min_lead_hours": 2 },
  "days": [
    { "date": "2026-11-03", "counts": { "online": 2, "physical": 1 },
      "slots": [
        { "id": "01k…", "starts_at": "2026-11-03T09:00:00+08:00", "duration_minutes": 60, "mode": "online", "state": "available", "location": null },
        { "id": "01k…", "starts_at": "2026-11-03T14:00:00+08:00", "duration_minutes": 60, "mode": "physical", "state": "booked", "location": { "name": "Klinik Damai", "city": "Kuantan" } }
      ] }
  ],
  "next_from": "2026-11-16"
}
```

- `display_name` is exactly what the list and the profile show to the same person (`CounselorPublicPresenter::displayName`).
- `avatar_url` is `null` for now (no photos are shown until there is an approval step). The field stays so adding photos later breaks nothing.
- `modes_offered` is what this person can see in the whole booking window, not only in the range asked for or the `mode` filter.
- Each day in the range is listed, even with no slots. `counts` are the **available** slots **returned** for that day, per mode (so with a `mode` filter the other mode counts 0).
- `slot.id` is the entry's random **public id** (a lowercase ULID), never a counter.
- `state` is `available` or `booked`. A booked slot carries nothing more than an available one: no client, no booking.
- `location` is the place's **name and city** for an in-person slot, otherwise `null`. A slot with no mode is **online**.
- `next_from` is the next date to ask for, or `null` at the horizon.

**Never in the answer:** the meeting link (it lives on the booking, never on the slot), the street address, postcode or coordinates, any client or booking detail, any price, any organization name or context, any private block, any row id.

## Who may ask, and the one answer for "no"

`401` signed out. `403` + `code: email_not_verified` for an unconfirmed address (every signed-in route now answers this the same way). `429` + `Retry-After` over the limit (`api-availability`: **60 a minute and 600 an hour per person**). `503` in maintenance, `426` for an outdated app.

For everything else that means "this counselor cannot be booked by this person" the answer is **one and the same**: status `404`, body `{"message": "This counselor is no longer available for booking.", "code": "counselor_unavailable"}`, same headers. The reasons it covers: an id that does not exist or is malformed, a counselor who is not listable (not approved, suspended, practising certificate expired, organization inactive), a **closed organization's counselor when the person is not an active member** (no `403 not_a_member`, which would reveal that the counselor and the organization exist), and **the counselor themselves** (nobody can book their own sessions; a counselor can book other counselors). The person's own organization is therefore never named here.

Who may see which counselor is decided in `CounselorDirectory` (`eligible()`, `visibleOrganizationIds()`), the same code the search and the profile use. `account_suspended` is **not** implemented: there is no account-level suspension yet (suspension exists for counselors, memberships and organizations, and a person whose membership is not active simply no longer sees a closed organization's counselors). Add the code when account suspension exists.

## The booking rules (one place: `AvailabilityService`)

Configurable in `config/booking.php`:

| Key | Default | Meaning |
|---|---|---|
| `min_lead_hours` | 2 | A slot starting sooner than this from now is not offered. Exactly 2 hours is allowed. |
| `horizon_days` | 30 | The last bookable day is today + 30 (Malaysian time). |
| `starting_soon_minutes` | 60 | The "Starting soon" badge on My bookings. |
| `max_upcoming_sessions` | 3 | Per person. Not enforced by this screen; the confirm step does. |
| `availability_days_per_request` | 14 | The longest range of one request. |

- **Time:** slots are stored as a date and a time with no zone and are Malaysian time. A day starts at Malaysian midnight, so a slot at 00:30 belongs to that Malaysian date.
- **Booked** means: the slot's `is_available` flag is off **or** a booking that is not cancelled points at it (two layers on purpose). Cancelled bookings free the slot.
- **A clash hides a slot.** A free slot is **left out** (never shown as "booked", which would reveal a private calendar) when it overlaps another entry on the **same counselor's** calendar: a **personal block** or a **booked slot of any context**, once the counselor's `buffer_minutes` is added to **both ends** of that other entry. Touching exactly at the edge is allowed. The organization the slot belongs to is ignored: a counselor is one person, so booking one context's slot hides an overlapping slot in another. A slot that is itself booked is shown as booked.
- **One function decides it,** `AvailabilityService`: `isFree()` (for the confirm step, slot creation and the approval of a requested time, so there is never a second implementation) and `withoutConflicts()` (a list at once). It reads the counselor's entries for the range in **one query** (plus one for the buffers) and compares in PHP: never a query per slot.
- **The profile and the search use the same window and the same clash rule,** so a time shown anywhere can be booked on this screen. **A change you will see:** the profile and the list used to show slots up to 60 days ahead and any time in the future; they now stop at 30 days and start 2 hours from now, and they no longer show a time that clashes with a block or a booked session.
- **Nothing is cached on the server.** The phone may keep an answer for 30 seconds (`Cache-Control: private, max-age=30`); no shared cache may (`private`, `Vary: Authorization`). A "no" is `no-store`.

## The confirm step (built)

Two endpoints, one set of rules. Code: `BookingController`, `BookingService`, `BookingIdempotency`; the rules are the SAME functions the availability screen uses (`CounselorDirectory::resolveSlot` / `resolveBookable`, `AvailabilityService::slotBookable`), so the preview and the booking cannot disagree. Tests: `BookingApiTest`, `BookingPublicIdTest`, `PurgeBookingIdempotencyKeysTest`, and (MySQL/MariaDB only) `BookingConcurrencyTest`.

### `GET /api/v1/bookings/preview?slot={public id}` (token, confirmed email)

Read only: claims nothing. Always `200` for a slot the person may book, with `valid` and `reason`:

| `valid` | `reason` | Body |
|---|---|---|
| true | null | the details below |
| false | `slot_taken` | **nothing else** (no details, never why) |
| false | `time_conflict` / `upcoming_limit_reached` | the details (they are about the person's own sessions) |

`404 counselor_unavailable` for everything else. Details: `slot`, `counselor{id, display_name, verified}`, `starts_at`, `ends_at` (ISO 8601 with +08:00), `duration_minutes`, `mode`, `location{name, city}|null`, `cancellation_deadline_at` (null when it is already past: the session cannot be cancelled after booking), `cancellation_hours`, `max_reschedules`, `meeting_link_visible_minutes`, and `upcoming_sessions` / `max_upcoming_sessions` **only** when the person holds the cap or one below it. No price, no meeting link, no street address, no organization name, no row ids. Its own limit: `api-booking-preview` (60 a minute, 600 an hour).

### `POST /api/v1/bookings` (token, confirmed email)

Header `Idempotency-Key` (a UUID, required). Body: `slot`, `acknowledged_policy` (must be the JSON value `true`; it is **not stored**), `expected_starts_at`, `expected_duration_minutes`, `expected_mode`. Limit `api-booking-create`: **10 a minute and 60 an hour** (`booking.create_rate_per_minute` / `_per_hour`).

`201`: `{booking: {id (random public id), status, starts_at, ends_at, duration_minutes, mode, counselor{display_name}, location{name, city}|null}}`. Never the meeting link, the address, organization names or other people's data.

| Status | `code` | Meaning |
|---|---|---|
| 401 | | signed out |
| 403 | `email_not_verified` | |
| 404 | `counselor_unavailable` | **one body** for: unknown or malformed slot, a personal block, a counselor not listable, a closed organization the person is not in, a counselor who left the slot's organization, the person themselves |
| 409 | `slot_taken` | **one body** for: taken, flag off, inside the lead time now, beyond the horizon, now overlapped by a block or a buffer |
| 409 | `slot_changed` | the counselor edited the slot (start, length or mode differ from `expected_*`). Body has `slot`: the fresh details. Nothing is booked |
| 409 | `time_conflict` | the person has a confirmed, not-yet-started session overlapping this time with ANY counselor (touching at the edge is fine) |
| 409 | `upcoming_limit_reached` | with `max_upcoming_sessions`. Only **confirmed** bookings that have **not started** count (`booking.max_upcoming_sessions`, 3) |
| 409 | `request_in_progress` | the same key is still being processed; `Retry-After` |
| 422 | | validation; or `idempotency_key_reuse`: the same key with a different request |
| 429 / 503 / 426 | | rate limit (with `Retry-After`) / maintenance / outdated app |

**The transaction, in this order** (always, so two bookings never wait for each other in a circle):

1. lock the booking person's row;
2. lock the counselor's profile row: this serializes **every** booking of that counselor, so two people taking two overlapping slots in two different contexts cannot both succeed;
3. decide again, with the locks held: who may book (`resolveSlot`) and whether the time can be booked (`slotBookable`): `slot_taken`;
4. compare `expected_*` with the real slot: `slot_changed`;
5. the person's limits: `time_conflict`, `upcoming_limit_reached`;
6. **claim:** one `UPDATE calendar_entries SET is_available = 0 WHERE id = ? AND is_available = 1 AND NOT EXISTS (a booking that is not cancelled)` and check that **exactly one row** changed (never read-then-write); otherwise `slot_taken`;
7. create the booking: `slot_based`, `confirmed`, `session_mode` from the slot (no mode = online), `org_id` from the slot's context, `location_id` from the slot, `reschedule_count` 0, no meeting link;
8. commit. Anything that fails before this rolls everything back, **the claim included**.

The e-mails (`BookingConfirmedClient`, `NewBookingCounselor`) are queued after the commit (`afterCommit`) and can never undo or fail the booking (an error is reported and the booking stands). The counselor's e-mail has the client's **display name only** (no e-mail, no phone, no link), and for an online session reminds them to add a meeting link. **Queue:** `QUEUE_CONNECTION=database`, so `php artisan queue:work` must be running or the e-mails wait; `MAIL_MAILER=log` in development. No push, no in-app notification, and **no audit entry** (the booking row is the record).

### Idempotency

Table `booking_idempotency_keys`, unique on (person, key); keeps a hash of the normalised request (never the request, a token or headers) and the answer of a successful booking for `booking.idempotency_ttl_hours` (24). The key is **reserved in its own short transaction before** the booking transaction. Same key + same request after a success: the stored answer, with the original status and `Idempotent-Replayed: true`. Different request: `422 idempotency_key_reuse`. Same key still running: `409 request_in_progress`. A refusal or a rollback **releases** the key (the person may try again with it). A reservation left "processing" (the server died) stops blocking after 60 seconds. `php artisan bookings:purge-idempotency [--dry-run]` (daily 03:30) deletes expired keys and writes one system audit entry with counts.

### Cancel, reschedule, offboard (the contract; cancelling and rescheduling by the client are built, the others are not)

Every action that ends a booking **must give the time back in the same transaction as the status change**: set `calendar_entries.is_available` back to 1. Cancel, reschedule, and a counselor or member leaving all do this. Otherwise the slot would stay "booked" forever (the flag is one of the two layers). Cancelling and rescheduling by the client do this (see "Cancelling" and "Rescheduling" below). Tests can use `AvailabilityFixtures::cancelBooking()` to say what "given back" means for the paths that do not exist yet.

### No ordinary unique index on `bookings.calendar_entry_id`, on purpose

A cancelled booking row stays, and its slot may be booked again, so an index over all rows would forbid that. A partial or filtered unique index is not portable across SQLite and MariaDB/MySQL. The safety gate is therefore **the two locks plus the conditional update with its affected-row check**. It is tested on MySQL/MariaDB only (`BookingConcurrencyTest`, real parallel processes; SQLite has no row locks or parallel connections), like the promo-code redemption test.

### Booking public id

`bookings.public_id`: a lowercase ULID, unique, required, never mass-assignable, set when the row is inserted (`performInsert`, so also in seeders that switch events off). The migration `2026_11_06_000001` is safe to repeat: it adds the column if missing, fills rows without an id in batches with no model events and no audit entries, then makes it required and unique. No sequential id is ever sent to the app.

### Configuration

`config/booking.php`: `cancellation_default_hours` (24; used only when the slot's organization has no value of its own: `organizations.cancellation_deadline_hours`, which defaults to 24 and is what the virtual platform organization has), `max_reschedules` (2), `meeting_link_visible_minutes` (15), `idempotency_ttl_hours` (24), `idempotency_processing_seconds` (60), `create_rate_per_minute` (10) / `_per_hour` (60), `preview_rate_per_minute` (60) / `_per_hour` (600), `max_upcoming_sessions` (3).

## My bookings (read only)

`GET /api/v1/bookings?scope=upcoming|past&page=` and `GET /api/v1/bookings/{id}` (token, confirmed email, the general `api-user` limit). Code: `MyBookingController`, `MyBookings`. Tests: `MyBookingsApiTest`.

- **Only the person's own bookings.** Another person's booking and one that does not exist are the same `404 booking_not_found`. The id is the booking's random public id (26 characters); the route only matches that shape, which also keeps it apart from `/bookings/preview`.
- **Upcoming** = `confirmed` and the session has not ended (a session under way is still upcoming, so "Join session" works), soonest first, all in one answer (the upcoming cap keeps it small). **Past** = everything else (completed, cancelled, no-show, and confirmed ones that have ended), newest first, 20 a page: `{data, page, last_page, total}`. Past excludes exactly the ids of the upcoming set, so no booking is in both or in neither.
- **A line** (`data[]`): `id`, `status` (`confirmed|completed|cancelled|no_show`), `starting_soon` (a confirmed session starting within `booking.starting_soon_minutes`, 60, or under way), `starts_at` / `ends_at` (+08:00), `duration_minutes`, `mode`, `counselor{id (the counselor's public id, null if they have no profile), display_name}`, `location{name, city}|null`.
- **The details** (`booking`) add: `cancellation_hours`, `cancellation_deadline_at` (null when passed or not confirmed), `can_cancel`, `reschedules_left`, `can_reschedule` (the cancelling rule AND moves left), `meeting` and `place`.
- **`meeting`** (online and confirmed only): `state` is `opens_later` (before the window), `available` (inside it and a link was added: the ONLY time `url` and `provider` are sent), `not_added` (inside it, no link yet) or `ended`; plus `opens_at` (`booking.meeting_link_visible_minutes` before the start) and `closes_at` (the end). The server decides; the app never works the window out for itself.
- **`place`** (in person and confirmed only): the FULL address (name, address, city, state, postcode, coordinates), for the owner only. A cancelled booking has neither a meeting nor a place.
- **Never in the answers:** an organization name, the person's own email or phone, the counselor's email, a row id, a price.
- **Cost:** a fixed number of queries however many bookings (a test counts them).
- **Nothing is cached:** `Cache-Control: private, no-store` (the answer can hold a meeting link).
- **In the app:** the Bookings tab (Upcoming / Past), Booking details with the session details, and "Booking confirmed" after a booking. Cancel and reschedule are built (see "Cancelling" and "Rescheduling").

## Cancelling

`POST /api/v1/bookings/{id}/cancel` (token, confirmed email; own limit `api-booking-cancel`: 10 a minute and 60 an hour). Code: `BookingCancellation`, `MyBookingController::cancel`. Tests: `BookingCancelApiTest`, and on MySQL/MariaDB only two real-process races in `BookingConcurrencyTest`.

- **The contract (B9) is real:** the status change (`confirmed` to `cancelled`) and giving the time back (`calendar_entries.is_available` on) happen in **one transaction**. A failure while giving the time back rolls the status back too (tested).
- **Only the person's own booking.** Someone else's and an unknown one are the same `404 booking_not_found`, and nothing changes.
- **The deadline** is the session's start minus the slot's organization's cancellation hours (`organizations.cancellation_deadline_hours`; `booking.cancellation_default_hours` when it has none). Exactly at the deadline it is closed; a second before it is open. Inside the window: `409 cancellation_closed` (with `cancellation_hours`), nothing changes, no e-mail. Completed, no-show, or a session that is already over: `409 not_cancellable`. **The same rule** (`BookingCancellation::rule`) gives the details' `can_cancel`, so the page and the action cannot disagree.
- **Safe to repeat, so no Idempotency-Key.** Cancelling a booking that is already cancelled answers the same booking again (`200`), **changes nothing and sends no second e-mail.** It must not touch the slot: someone else may have booked the freed time meanwhile (tested). A lost answer is simply asked again.
- **Lock order** is the same as booking a time (the person, the counselor's profile, then the booking), so a cancel and a booking of the same counselor never wait for each other in a circle (tested with real processes).
- **The e-mail** to the counselor (`BookingCancelledCounselor`): queued after the commit, the client's display name only, the date, time and kind, and that the time is open again. It can never undo or fail the cancellation. No e-mail to the client (they just did it), no audit entry (B12), no push (B11).
- **Answer:** `200 {booking: <the details, now cancelled: no meeting, no place, can_cancel false>}`.
- **Not built:** a cancelled booking is not "refunded" (there is no payment), and the counselor cancelling or offboarding is a separate path that must follow the same contract.

## Rescheduling

`POST /api/v1/bookings/{id}/reschedule` (token, confirmed email; own limit `api-booking-reschedule`: 10 a minute and 60 an hour). Body: `slot` (the new time's public id) and what the person saw: `expected_starts_at`, `expected_duration_minutes`, `expected_mode`. Code: `BookingRescheduling`, `MyBookingController::reschedule`. Tests: `BookingRescheduleApiTest`.

- **The contract (B9) is real:** the new time is claimed (the same conditional UPDATE as booking) and the old time given back (`is_available` on) in **one transaction**. A failure at any step rolls the whole move back (tested).
- **Same booking, new time.** The booking keeps its public id and counselor; its time, mode, place and organization follow the new slot, and `reschedule_count` goes up by one. Another counselor: `422 different_counselor`. (Booking another counselor is a new booking.)
- **The same time limit as cancelling** (decided by the owner): `BookingCancellation::rule` decides both, measured from the OLD session's start. Inside the window: `409 reschedule_closed` (with `cancellation_hours`). At most `max_reschedules` moves: `409 reschedule_limit_reached` (with `max_reschedules`). Completed, no-show, cancelled or already over: `409 not_reschedulable`. The details' `can_reschedule` is the same rule plus moves left.
- **The new time** goes through the same checks as a booking: who may see the counselor (`404 counselor_unavailable`, the same "no" for everything private), whether the time can be booked (`409 slot_taken`, one answer for every reason), whether it was edited after the person saw it (`409 slot_changed` with the fresh details), and whether it overlaps another upcoming session of the person (`409 time_conflict`; the session being moved does not count against itself). The upcoming-session cap does not apply: one session replaces another.
- **Safe to repeat, so no Idempotency-Key.** Asking for the time the booking already has answers the same booking (`200`), changes nothing, counts nothing and sends no second e-mail. It must not touch the old time: someone else may have booked it meanwhile (tested).
- **Lock order** is the same as booking and cancelling (the person, the counselor's profile, then the booking).
- **The e-mail** to the counselor (`BookingRescheduledCounselor`): queued after the commit, the client's display name only, the old and new date and time and kind, and that the old time is open again. It can never undo or fail the move. No e-mail to the client, no audit entry (B12), no push (B11).
- **Answer:** `200 {booking: <the details at the new time>}`.
- **Not built:** the counselor moving a booking, and a MariaDB race test for moving (the claim and the locks are the ones booking and cancelling already have race tests for).

## The counselor's sessions (web portal)

`/counselor/appointments` (role counselor). Code: `CounselorSessions`, `Counselor\CounselorAppointmentController`. Tests: `CounselorSessionsTest`.

- **Three lists:** Needs marking (confirmed and already ended), Upcoming (confirmed, not ended) and Past (completed, no-show, cancelled; 20 a page). Only the counselor's own bookings; someone else's is a 404. The client is shown by **display name only** (B3).
- **Marking:** `POST /counselor/appointments/{id}/outcome` with `completed` or `no_show`. **Only the counselor says what happened**: nothing is assumed from the clock. Allowed only from confirmed and only once the session has ended (to the minute). The same outcome again changes nothing; another outcome once set is refused. It never touches `is_available`. The client's app then shows it under Past.
- **The meeting link:** `PUT /counselor/appointments/{id}/meeting` (app from a short list, link `https` only, 255 characters). Only for a confirmed **online** session that has not ended. An empty link removes it. This is the only source of the link the client's app shows 15 minutes before the session (until now a link only existed if set in the database).
- **No e-mail, no audit entry (B12).**
- **Not built:** the planner (opening and editing times), the counselor's dashboard numbers, session notes, and the counselor cancelling a booking (that path must give the time back, B9).


- **Who writes `is_available`:** no code in `app/` sets it to false at all. Only three **demo seeders** do, and each only alongside a booking (one of them leaves it off for a *cancelled* past booking, harmless because it is in the past; the rule in step 4 is what prevents it for real data). So "flag off" and "booked" mean the same thing today. If code ever turns the flag off for another reason (a counselor closing a slot by hand), that slot must be **left out** of the answer, not shown as "booked" (which would reveal a private reason), and this document and `AvailabilityService::isBooked()` must be revisited.
- **Random ids for slots:** `calendar_entries.public_id` (a lowercase ULID, unique, required, never mass-assignable). The migration is safe to repeat, fills existing rows in batches without model events or audit entries, then makes the column required. The model sets the id when an entry is inserted (not in a model event), so it also holds in the database seeders, which switch events off. **Counselor profiles had the same hole:** after the full seed their `public_id` stayed empty. That is fixed the same way; a development database seeded before this change can still hold profiles without an id: `php artisan migrate:fresh --seed` rebuilds it.
- **`accepts_requests`** is returned so the app can offer "Request a time" later; the Mode B screen does not exist, so the app hides that link until it does.

## For the app

The Flutter side is described with its own screen. Keep the mapping of `physical` → "In-person" in **one** place in the app, and show Malaysian time (the offset in the string), never the phone's time zone.

## Demo data

`php artisan db:seed --class=AvailabilityDemoSeeder` (local and testing only; run by hand, not part of `db:seed`). Counselors offering both modes, online only, a closed university's, an open clinic's, an independent one, a suspended one and one with an expired practising certificate; slots that are booked (one online booking with no meeting link yet), in the past, inside the lead time, beyond the horizon, and under a personal block; and three people: an active member, a non-member and someone whose email is not confirmed. Everything is marked "Demo" / `@example.test`, with unusable passwords: act as them in a test with `Sanctum::actingAs`.

## Tests

`AvailabilityApiTest` (access, the single "no" answer, the window to the minute, paging, midnight, modes and counts, booked in every layer, blocks and buffers and two contexts, contexts and names, the profile agreeing with the screen, the shape, nothing private, cache headers, the limit, a fixed number of queries), `AvailabilityServiceTest` (the clash rule to the minute, what counts as busy, one query for a whole list), `CalendarEntryPublicIdTest` and `PublicIdsAfterFullSeedTest` (the ids and the migration on existing data), `AvailabilityDemoSeederTest`, `BookingRulesConfigTest`. All also pass on MariaDB.
