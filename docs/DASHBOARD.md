# Platform Admin Dashboard

Everything the Dashboard shows comes from **one class, `App\Services\DashboardService`**, which only **composes** the methods the owning pages already use. It repeats none of their logic, so a number on the Dashboard and the same number on its page can never disagree. Nothing is kept between requests (**no cache**): an admin who approves, suspends or switches something sees the new number on the very next read.

Built in four steps. **Step 1 (done): the audit. Step 2 (done): the service and its tests. Step 3 (done): the view is wired to the service, with an empty state for every widget. Step 4 (done): the remaining hooks, docs and the final run on SQLite and MariaDB.**

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

## The page (Step 3)

`PlatformDashboardController` only calls `DashboardService::build()` and hands the result to the view; nothing is counted or hard-coded in the view. The design is unchanged. What was **added**: the sign-up banner, the "By" column in Recent approvals, the amber/red wait colours, the "more waiting. View all" line, and the "No session data yet" note. What was **changed in wording**: "This month" is now "Overall" (three of the four numbers are totals), and "Bookings this month" is now "Sessions this month".

- **Cards** link to the tab they count. The complaints card shows the combined number and "N open, M under review". The badge next to "Needs your attention" is the sum of the four cards, and they no longer overlap.
- **Waiting for review** lists the attention items (name, how long, "Review →" to the exact record). When there are more than 8 it says how many more and lists where they are, with links ("View all"). Empty: "Nothing is waiting for review. You're all caught up."
- **Banner:** the sign-up strip is shown only when a sign-up is closed (which kinds, and how many are on the waitlist). **Maintenance mode** already has its own bar on every Platform page, so it is not repeated here (a test checks it appears once). Hidden when everything is normal.
- **Platform stats** keep the donut; "No session data yet" appears under it until a session has been marked completed.
- **Recent approvals** empty state: "No approvals yet. They appear here as they are made."
- **Greeting** follows the admin's timezone.

## Hooks and the header (Step 4)

| Part | What it does now |
|---|---|
| **"Ask anything" box** | **Switched off.** The field, Attach and Send are disabled and the box says "Coming soon". It sends nothing anywhere and returns no answers. The typewriter placeholder and the line "Powered by AI — ask in plain language to search or filter platform data" were removed because they promised something that does not exist. When a real feature exists it replaces this component's disabled state. |
| **Quick-action pills** | Ordinary links: Review organizations → Organizations, Pending tab; Review counselors → Counselors, Pending tab; View complaints → Complaints, Open tab; Audit log; LKM directory. |
| **Search icon / Ctrl+K** | A **page jumper**, not a search. It lists the sidebar destinations (`config/platform-nav.php`) and filters them by name. It does **not** search any data: no organization, counselor, complaint or person. Its wording says so ("Go to a page…"). Moderate Feed shows as "Soon" until it exists. |
| **Bell** | Switched off ("Notifications — coming soon") and the permanent red dot was removed, because it claimed something was waiting. Give it a real source (and a real dot) when notifications exist. |
| **Avatar menu** | Name, email and "Platform Admin" come from the signed-in user. Account settings, Security, Account activity, Audit log and Notification settings all open (tested). "Language" and "Help & support" are still "Soon". |
| **Logo** | Unchanged: the icon (`public/images/mykaunsel-icon.png`) with the wordmark that slides out on hover, linking to the Dashboard. Kept as it was (your decision); swap it for `public/images/mykaunsel-logo.png` in `topbar.blade.php` if you want the full logo there. |

## Query cost

`DashboardService::build()` runs **26 queries** (SQLite measurement) and a full Dashboard load **27**; neither grows with the data (tests fill the database and compare). The limit is **40** queries for a full page load (`DashboardPageTest::MAX_QUERIES`): going over it means an N+1 crept in. About 5 of the queries belong to `AnalyticsService`.

## Tests

- `DashboardServiceTest`: cards, thresholds to the hour, ordering and stability, the cap and `more`, a more urgent item never cut off by another source, each item kind, every link opens, the stats and the changed definitions, recent approvals (order, saved names, no back-fill, the audit filter), the banner's three states, the greeting in different timezones and at noon/six, an empty database, nothing private in the output, and a constant query count.
- `DashboardPageTest`: access (platform admin only; organization admin and student get 403), every widget with an empty state, the greeting in two timezones, cards and links, wait colours and order, the cap and "View all", the new stat labels, the approvals table and its timezone, the banner states, nothing private in the HTML (complaint text, document names, notes), no design sample text left in the views, and the query limit.
- `DashboardMatchesPagesTest`: on the real sample data each card equals the count on its page's tab; approving an organization, suspending a counselor, closing a registration type and taking up a complaint each change the very next read.
