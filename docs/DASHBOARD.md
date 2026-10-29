# Platform Admin Dashboard

Everything the Dashboard shows comes from **one class, `App\Services\DashboardService`**, which only **composes** the methods the owning pages already use. It repeats none of their logic, so a number on the Dashboard and the same number on its page can never disagree. Nothing is kept between requests (**no cache**): an admin who approves, suspends or switches something sees the new number on the very next read.

Built in four steps. **Step 1 (done): the audit. Step 2 (done): the service and its tests (no screen changed).** Step 3: wire the view. Step 4: remaining hooks, docs, final run.

## Where each figure comes from

| Dashboard figure | Source (the same one its page uses) |
|---|---|
| Pending organizations | `Organization::applicants()->awaitingReview()` = Organizations → **Pending** tab |
| Skipped DNS verification | `Organization::applicants()->skippedVerification()` = Organizations → **Skipped** tab |
| Pending counselors | `CounselorListFilters::forTab(…, 'pending')` = Counselors → **Pending** tab |
| Active complaints | `Complaint::withStatus(Open)` + `withStatus(UnderReview)` = Complaints → **Open** tab + **Under review** tab (shown with both numbers) |
| Platform Stats | `AnalyticsService` (see "Definitions that changed") |
| Recent approvals | the **audit log only** |
| Banner | `PlatformSettings` (Registration Control) and `WaitlistService` |
| Greeting | the admin's own timezone (Account page) |

Each card's link opens the tab it counts (`?tab=pending`, `?tab=skipped`, `?tab=open`).

## Definitions that changed

These differ from what the Dashboard counted before. They now match the pages.

| Figure | Before | Now |
|---|---|---|
| Pending organizations | every pending organization, **including** those that skipped DNS (so the same organization was in two numbers, and the "needs attention" badge counted it twice) | only the Pending tab; the skipped ones are on their own card |
| Organizations (stats) | active only | **active + suspended** (approved), the virtual platform organization excluded. Same as Analytics and the Organizations → Approved tab |
| Counselors (stats) | approved only | **approved + suspended** (same as Analytics) |
| Active members | membership **rows** (a person in two organizations counted twice) in any organization | **distinct people** with an active student, staff or employee membership in a **real** organization (the platform organization is excluded). `AnalyticsService::activeMembers()` |
| "Bookings this month" | bookings **created** this month (UTC month), every status | **"Completed sessions this month"**, counted by **session date** in the admin's timezone. Shows "No session data yet" until a session has ever been marked completed or no-show (`AnalyticsService::anySessionOutcomes()`) |
| Waiting thresholds | written twice (organization and counselor models) | one place, `App\Support\WaitingTime`: **5 whole days or more = amber, 8 or more = red** |
| Recent approvals | name only | name, type, **who approved it**, date in the admin's timezone; "View all" opens the Audit Log filtered to organization and counselor approvals (`?actions=organization.approved,counselor.approved`) |

The Organization detail page still counts "sessions this month" by booking creation date; it was not part of this work.

## Needs your attention

Up to **8 items**, most urgent first: **red before amber before the rest**, then the **longest wait first**, then a fixed key, so the order is the same on every load. `total` counts every eligible item, `more` how many were not shown, and `more_by_destination` says where they are (one line per page, with its link). There is no single "all items" page, so "View all" is that list of destinations.

| Item | When it appears | Links to |
|---|---|---|
| Organization awaiting approval | Pending tab, waiting **5 days or more** | the organization |
| Domain not verified | pending and skipped DNS verification, **any age** (a person must check it by hand) | the organization |
| Counselor awaiting verification | pending, waiting 5 days or more | the counselor |
| Counselor, LKM number could not be verified | pending, **any age**; shown instead of the waiting line, once per counselor, with the KB number | the counselor |
| Duplicate KB registration attempt | any account (any status) someone tried to register the KB number of again; age = the last attempt | the counselor |
| Complaint open | status **Open** (not yet taken up), any age; reference number only | the complaint |
| N counselors with PA expiring within 30 days | one summary line (the Counselors → Active filter `pa=expiring`) | that filtered list |
| N organizations with a plan ending within 30 days | one summary line (`PricingStats::subscriptionsEndingWithin(30)`) | Organizations → Approved |

Because the two summary lines have no waiting time, they come after the timed items and may fall outside the first 8; they are then counted in `more_by_destination`.

**Privacy:** names, KB numbers and reference numbers only. The service never loads a complaint's text (its query does not select it), a document name, a note or any chat. A test puts such text into the data and fails if any of it appears in what the service returns.

**Cost:** each source loads at most 8 rows, already oldest first, plus one count, so the final cut can never miss a more urgent row and the number of queries does not depend on how much data there is.

## Recent approvals

Latest 5 `organization.approved` and `counselor.approved` entries, newest first (ties by id). The name and the actor are the ones **saved in the entry at the time**, so a renamed or deleted record still shows as it was, and a system job shows as "System". **Nothing is back-filled**: approvals made before the audit log existed do not appear, which is expected.

## Banner

`banner()` returns one of three states:

- `maintenance`: maintenance mode is on (with the reopen time in the admin's timezone, if one is set). Wins over everything.
- `registration`: one or more kinds of sign-up are closed. Carries the closed kinds, whether **all** are closed, and the waitlist numbers: only people with status **Waiting**, only **Organization** and **Counselor** (individuals have no waitlist), and only for the kinds that are closed.
- `hidden`: everything is normal.

## Query cost

`DashboardService::build()` runs **26 queries** (SQLite measurement), and the number does **not** grow with the data (a test fills the database and compares). About 5 of them belong to `AnalyticsService`. The page limit for a full load is chosen in Step 3 once the view is wired.

## Tests

- `DashboardServiceTest`: cards, thresholds to the hour, ordering and stability, the cap and `more`, a more urgent item never cut off by another source, each item kind, every link opens, the stats and the changed definitions, recent approvals (order, saved names, no back-fill, the audit filter), the banner's three states, the greeting in different timezones and at noon/six, an empty database, nothing private in the output, and a constant query count.
- `DashboardMatchesPagesTest`: on the real sample data each card equals the count on its page's tab; approving an organization, suspending a counselor, closing a registration type and taking up a complaint each change the very next read.
