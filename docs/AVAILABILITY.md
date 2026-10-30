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
| `max_upcoming_sessions` | 3 | Per person. **Not enforced here**; the confirm step will. |
| `availability_days_per_request` | 14 | The longest range of one request. |

- **Time:** slots are stored as a date and a time with no zone and are Malaysian time. A day starts at Malaysian midnight, so a slot at 00:30 belongs to that Malaysian date.
- **Booked** means: the slot's `is_available` flag is off **or** a booking that is not cancelled points at it (two layers on purpose). Cancelled bookings free the slot.
- **A clash hides a slot.** A free slot is **left out** (never shown as "booked", which would reveal a private calendar) when it overlaps another entry on the **same counselor's** calendar: a **personal block** or a **booked slot of any context**, once the counselor's `buffer_minutes` is added to **both ends** of that other entry. Touching exactly at the edge is allowed. The organization the slot belongs to is ignored: a counselor is one person, so booking one context's slot hides an overlapping slot in another. A slot that is itself booked is shown as booked.
- **One function decides it,** `AvailabilityService`: `isFree()` (for the confirm step, slot creation and the approval of a requested time, so there is never a second implementation) and `withoutConflicts()` (a list at once). It reads the counselor's entries for the range in **one query** (plus one for the buffers) and compares in PHP: never a query per slot.
- **The profile and the search use the same window and the same clash rule,** so a time shown anywhere can be booked on this screen. **A change you will see:** the profile and the list used to show slots up to 60 days ahead and any time in the future; they now stop at 30 days and start 2 hours from now, and they no longer show a time that clashes with a block or a booked session.
- **Nothing is cached on the server.** The phone may keep an answer for 30 seconds (`Cache-Control: private, max-age=30`); no shared cache may (`private`, `Vary: Authorization`). A "no" is `no-store`.

## The contract for the confirm step (NOT built here)

The confirm endpoint, when it is built, must:

1. Accept an **`Idempotency-Key`** header, so a retry of the same request (a bad network) does not book twice.
2. **Claim the slot with one atomic conditional update inside a transaction:** `UPDATE calendar_entries SET is_available = 0 WHERE id = ? AND is_available = 1`, and check the **number of rows affected** (do not read, then write). Zero rows = someone else got it: answer `409` with `code: slot_taken`.
3. Re-run the eligibility rules (who may book this counselor, a confirmed email, not their own sessions) and the clash rule (`AvailabilityService::isFree()`, ignoring the slot being booked), then create the booking, in the same transaction. Only then the app shows "Booking confirmed". The booking's organization comes from the **slot's context**, and the per-person cap (`max_upcoming_sessions`) and overlapping-bookings rules are enforced here.
4. **Every action that ends a booking must give the time back.** Cancel, reschedule and offboarding (a counselor or member leaving) **set `is_available` back to 1** in the same transaction as the status change. Otherwise the slot would stay "booked" forever (the flag is one of the two layers).
5. Answer `409 slot_taken` also when the slot was hidden meanwhile by a clash.

**No ordinary unique index on `bookings.calendar_entry_id`, on purpose.** A cancelled booking row stays, and its slot may be booked again, so an index over all rows would forbid that. A partial or filtered unique index is not portable across SQLite and MariaDB/MySQL. The safety gate is therefore the conditional update in step 2 with its affected-row check. It must be tested **on MySQL/MariaDB only** (it needs real row locks and parallel connections; SQLite has neither), like the promo-code redemption test.

## Findings that shaped this

- **Who writes `is_available`:** no code in `app/` sets it to false at all. Only three **demo seeders** do, and each only alongside a booking (one of them leaves it off for a *cancelled* past booking, harmless because it is in the past; the rule in step 4 is what prevents it for real data). So "flag off" and "booked" mean the same thing today. If code ever turns the flag off for another reason (a counselor closing a slot by hand), that slot must be **left out** of the answer, not shown as "booked" (which would reveal a private reason), and this document and `AvailabilityService::isBooked()` must be revisited.
- **Random ids for slots:** `calendar_entries.public_id` (a lowercase ULID, unique, required, never mass-assignable). The migration is safe to repeat, fills existing rows in batches without model events or audit entries, then makes the column required. The model sets the id when an entry is inserted (not in a model event), so it also holds in the database seeders, which switch events off. **Counselor profiles had the same hole:** after the full seed their `public_id` stayed empty. That is fixed the same way; a development database seeded before this change can still hold profiles without an id: `php artisan migrate:fresh --seed` rebuilds it.
- **`accepts_requests`** is returned so the app can offer "Request a time" later; the Mode B screen does not exist, so the app hides that link until it does.

## For the app

The Flutter side is described with its own screen. Keep the mapping of `physical` → "In-person" in **one** place in the app, and show Malaysian time (the offset in the string), never the phone's time zone.

## Demo data

`php artisan db:seed --class=AvailabilityDemoSeeder` (local and testing only; run by hand, not part of `db:seed`). Counselors offering both modes, online only, a closed university's, an open clinic's, an independent one, a suspended one and one with an expired practising certificate; slots that are booked (one online booking with no meeting link yet), in the past, inside the lead time, beyond the horizon, and under a personal block; and three people: an active member, a non-member and someone whose email is not confirmed. Everything is marked "Demo" / `@example.test`, with unusable passwords: act as them in a test with `Sanctum::actingAs`.

## Tests

`AvailabilityApiTest` (access, the single "no" answer, the window to the minute, paging, midnight, modes and counts, booked in every layer, blocks and buffers and two contexts, contexts and names, the profile agreeing with the screen, the shape, nothing private, cache headers, the limit, a fixed number of queries), `AvailabilityServiceTest` (the clash rule to the minute, what counts as busy, one query for a whole list), `CalendarEntryPublicIdTest` and `PublicIdsAfterFullSeedTest` (the ids and the migration on existing data), `AvailabilityDemoSeederTest`, `BookingRulesConfigTest`. All also pass on MariaDB.
