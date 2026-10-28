# Analytics (Platform Admin → Platform → Analytics)

Platform-wide **trends**, read-only, **aggregates only**. It never shows an individual's data, chat content or session details. Everything is worked out from the live tables by one class, `App\Services\AnalyticsService`: there is no statistics table that can go stale, and the Dashboard's Platform Stats widget will read the same service, so the numbers always match.

Built in three steps. **Step 1 (done): the service, the range class, the demo seeder and their tests.** **Step 2 (done): the page.** Step 3: export, caching, audit entry for the export, Dashboard hook documentation.

## The page (Step 2)

`Platform → Analytics`, route `platform.analytics` (`GET /platform/analytics`, platform admins only, 403 for anyone else, throttled). Opening it is **not** audited.

- **Range in the URL:** `?range=7|30|90` or `?range=custom&from=YYYY-MM-DD&to=YYYY-MM-DD`. Default 30 days. An unknown value falls back to the default; an invalid custom range (reversed, future end, over 366 days, not a date, missing, or sent as an array) shows the reason and the default range, never an error page.
- **Controls:** the three quick ranges, custom dates, "Updated …", **Refresh** (reloads with the same range) and an **Export** button that is shown disabled until Step 3.
- **Four cards:** approved organizations, approved counselors, sessions completed, no-show rate. Each trend is written as arrow + sign + percent ("▲ +12.5% vs previous 30 days", spoken text for screen readers) and "—" when there is nothing to compare with.
- **Four charts**, all server-rendered SVG (no chart library): growth (line, two series), sessions by organization type (bars), specializations (bars; "Sessions" and "Offered" views) and application outcomes (stacked bars). Every chart has a plain-text summary, a **View as table** toggle with the same numbers, and a title (hover) on each mark.
- **Not colour alone:** the two growth lines differ by dash and marker shape and are in the legend; a hidden "<5" bar is a hatched stub (it carries no size); outcome segments have a legend with counts and percentages, and "pending" is hatched.
- **Distinct empty states:** nothing approved yet; no session data **yet** (the attendance step is not built); no completed sessions **in this period**; no specializations recorded; no applications submitted in this period.
- **Loading:** a skeleton is shown while a new range or a refresh loads.
- **Notes shown on the page:** the "live data / demo data" sentence, sessions without a date, "N suspended currently", "N without a recorded approval date", "Rejected is the current status", "Resubmissions: not tracked yet" for organizations, and the "<5" explanation.
- **Not checked in a browser yet:** the layout, colours and focus rings follow the existing Platform style but have not been looked at on a screen. New Tailwind classes need `npm run build` (or `npm run dev`) to appear.

## Privacy rules (the page, the table views and the export)

- Counts, percentages and labels such as "University" only. **No name, email, KB number or id of a person or an organization**, no link from a chart to a record. A test walks the whole result and fails on any such key or value.
- **No leaderboards** and no per-counselor or per-organization performance ranking.
- **Small numbers are hidden.** A count from 1 to 4 (the threshold is `analytics.small_number_threshold`, default 5) is not shown as a number:
  - in the **specialization** views the category is grouped into **"Other (fewer than 5)"**, its name is never listed, and if the total of the "Other" group is itself under the threshold it is shown as "<5" too;
  - in the **sessions by organization type** view the value is shown as **"<5"** (the four kinds are fixed, so they cannot be grouped). A hidden count carries **no number at all** in the result, so no later layer (page, table view, export) can print it by mistake. **0 is shown as 0** (it identifies nobody).
  - **Limit, stated plainly:** if a card shows a total, a hidden value can be worked out by subtracting the visible ones. This is meant to stop **casual** identification, not to be formal anonymity, and the page is for Platform Admins only. It is a known limit, not something this page tries to solve.
- No chat, message, complaint or session-note text is read at all.
- **No tracking of people who "started" registering.** There is no data for it and tracking unauthenticated visitors would be a privacy matter. "Application outcomes" only looks at applications that were actually submitted.

## Definitions (one place: `AnalyticsService`)

| Figure | Definition |
|---|---|
| **Approved organization** | status **active or suspended** (it was approved; "suspended" is how many of those are **currently** suspended). The virtual **platform organization is never counted**. Counted **as of** a moment when its approval date (`reviewed_at`) is on or before it. |
| **Approved without an approval date** | An approved organization with no `reviewed_at` is counted at **every** moment, in **both** the selected and the previous period, so the trend is not affected, and the page says "N without a recorded approval date". No date is invented. |
| **Approved counselor** | status approved or suspended, independent and organization counselors, counted the same way. **Organization counselors have no approval flow, so they have no date**: they fall under the rule above. The card splits independent versus organization and shows how many are (currently) suspended. |
| **Suspended** | The **current** number (the history of suspensions is not kept: reactivating clears `suspended_at`). The page labels it "currently". |
| **Session date** | `calendar_entries.entry_date` + `start_time`, the Malaysian wall-clock time of the slot (`counselors.directory.slot_timezone`, **Asia/Kuala_Lumpur**), turned into an instant with that IANA zone. A booking has no date of its own. **A booking with no calendar entry has no date**: it is left out and counted ("sessions without a date" note). |
| **Sessions completed** | bookings whose status is **explicitly `completed`** and whose session falls in the range. **A session is never assumed completed because its time has passed.** |
| **No-show rate** | `no_show / (completed + no_show)`, one decimal. **Cancelled sessions are not in it** (their number is returned for the tooltip). No sessions: `null` ("—", "No session data yet"), never a division by zero. |
| **Trend** | Percentage change against the previous period **of equal length immediately before**. `null` ("—") when the previous value is 0 or unknown. Never divides by zero. |
| **Organization type of a session** | `bookings.org_id` → `organizations.org_type`: university, corporate, clinic; the **virtual platform organization means Independent**; a booking with no organization at all is its own bar "No organization recorded", shown only when there is one. |
| **Growth chart** | cumulative approved organizations and counselors **at the end of each bucket**, by `reviewed_at`; the starting level includes the "no date" ones. Flat at zero on both lines → `empty` (the page shows the empty state instead of a chart). |
| **Specializations (sessions)** | Each completed session counts **once for each distinct specialization of its counselor** (so the numbers are sessions, not a share of a total). Specializations are free text: **trimmed, spacing collapsed, capitals ignored, nothing else merged** (no synonyms: "Stress" and "Stres" stay separate). The label of a merged group is its **most common spelling, ties broken by the alphabet**, so it never changes between page loads. |
| **Specializations (offered)** | How many **approved** counselors list each specialization (counselors, not sessions; a counselor who wrote it twice counts once). Available before any session exists. It is the situation **now** (specializations have no history). |
| **Application outcomes** | A **cohort**: applications **submitted in the range** (`submitted_at`, the latest submission), organizations (the platform organization excluded) and **independent (platform-verified) counselors** separately. Submitted = Approved (active or suspended) + Rejected + Still pending **as of now**. **"Rejected" is the current status**: an earlier rejection that was followed by a resubmission cannot be recovered, because the decision fields are overwritten. A counselor without `submitted_at` (documents not sent yet) is not in the cohort. |
| **Median decision time** | for **decisions made in the range** (`reviewed_at`), `reviewed_at − submitted_at`, **only when `reviewed_at` is later than `submitted_at`** (after a resubmission `reviewed_at` still holds the old decision). An even number of decisions: the mean of the two middle ones. None: `null`. |
| **Resubmissions** | independent counselors in the cohort with `submission_count ≥ 2`. **Organizations: `null` ("—, not tracked yet")**: nothing in the application raises an organization's `submission_count` (the way for a rejected organization to apply again is not built). It is not 0. |

## Dates, ranges and buckets

- A range is **whole days (00:00:00 to 23:59:59) in the admin's timezone** (the Account page; an IANA name, never a fixed offset). The same moment can be a different day for two admins, and the tests cover it (midnight in Kuala Lumpur versus UTC, and a zone with daylight saving).
- Choices: last 7 / 30 (default) / 90 days (ending today, today included), or custom from/to. Validation: from ≤ to, **no future end date**, **at most 366 days**. The range is in the URL (step 2).
- **Bucket**: daily up to 31 days, weekly up to 180 days (Monday to Sunday), monthly beyond. The first and last bucket are cut to the range.
- The database only does plain comparisons, counts and `group by` on plain columns (no `strftime`, `date_trunc`, `DATE_FORMAT`). **Days, weeks and months are built in PHP.** The database keeps UTC; `AnalyticsRange::utc()` is the one place that turns an admin-timezone moment into database text.
- The slot date is Malaysian but the range may be in another zone, so the query asks for a day either side and the exact cut is made in PHP on the instant.

## Why the session cards will be empty on real data (for now)

**Nothing in the application sets `completed` or `no_show`**: the attendance step (a counselor marking a session done or missed) is not built. Only `confirmed` and (when a counselor is suspended) `cancelled` are ever written. Because a session is never assumed completed, the sessions cards, the no-show rate, the sessions-by-type chart and the specialization-by-sessions view show **"No session data yet"** until that step exists. Only the demo data fills them. The "Offered" specialization view, the organization/counselor cards, the growth chart and the application outcomes work from real data today.

## Conflicts with the Dashboard (to settle when it is connected)

The Dashboard's Platform Stats widget is **not changed** by this work. It counts by itself today, with different rules, so when it is connected to `AnalyticsService` (the methods are there: `totalOrganizations()`, `totalCounselors()`, `activeMembers()`, `completedSessionsThisMonth()`) **its numbers will change**:

| Widget today | After it uses the service |
|---|---|
| Organizations = **active only** | **active + suspended** ("N suspended" beside it) |
| Counselors = all approved | approved + suspended |
| Members = active students, staff, employees (memberships) | the same rule (`activeMembers()`) |
| "Bookings this month" = bookings **created** this month | "Completed sessions this month, **by session date**" (`completedSessionsThisMonth()`), which is 0 until sessions can be completed |

## Demo data

`php artisan db:seed --class=AnalyticsDemoSeeder` (also part of `db:seed`; **local and testing only**, it stops with an error anywhere else). About six months: 15 organizations in all states (one approved without a date, one suspended), 16 independent counselors and 4 organization counselors (one resubmitted, one suspended, one without a submission date, a lower-case spelling of one specialization, two rare specializations with 3 and 2 sessions), and about 115 completed / no-show / cancelled sessions for the four kinds of organization plus 3 still to come. Everything is marked as demo (`[Demo]` organizations, `Demo …` names, `@example.test` emails) and nobody can sign in as a demo person (random passwords). Safe to run twice (same data).

## Files

`app/Services/AnalyticsService.php` (all figures) · `app/Support/AnalyticsRange.php` (the range, buckets, previous period, cache key) · `app/Exceptions/InvalidAnalyticsRange.php` · `config/analytics.php` · `database/seeders/AnalyticsDemoSeeder.php` · tests: `AnalyticsRangeTest`, `AnalyticsServiceTest`, `AnalyticsDemoSeederTest` (+ `AnalyticsFixtures`).

## Tests on MySQL

Run on MariaDB 10.4 as well (`bash tool/test-on-mysql.sh`, see `MYSQL_TESTING.md`): the grouping of dates is where SQLite and MySQL differ most, which is why days, weeks and months are built in PHP.
