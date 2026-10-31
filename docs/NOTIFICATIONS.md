# Notification center (Platform Admin bell)

An **in-app inbox of things that happened** ("event-based"). It is separate from the Dashboard's "Needs your attention" list ("state-based": what is pending right now). A notification links to the item; it never replaces the pending lists.

Built in three steps. **Step 1 (done): storage, registry, service, de-duplication, coalescing and their tests (no screen).** **Step 2 (done): the bell, the panel, polling and the redirect route.** **Step 3 (done): the full page, the producers on the existing events, retention, the demo seeder and these docs.**

## Decisions

| Question | Decision |
|---|---|
| Storage | A **dedicated table** `user_notifications` with real columns (not Laravel's `notifications` table, whose JSON `data` column cannot be filtered, de-duplicated or purged the same way on SQLite and MariaDB, and which would tie the email and the in-app item to one class while the Account preferences must control email only). |
| Who receives | Every **active** Platform Admin (a `platform_admin` membership with status `active` **in the platform organization**), except the person who did the thing. A removed (`offboarded`) or suspended admin gets nothing. |
| In-app vs email | An in-app item is **always** created for every type. The Account-page preferences control **email only**, and the Account page is not changed. |
| Failure | Publishing is **best effort**: `NotificationCenter::publish()` never throws; a failure is `report()`ed and the action that caused it goes on. Producers call it after the commit, from the same place that sends the email. |
| Coalescing | Repeated duplicate-KB attempts: **one notification per KB per 24 hours** (config `notifications.coalesce_hours`), counted from the **first** attempt. A new attempt inside the window raises the count, moves `occurred_at` and makes it unread again; an attempt after the window starts a new notification and leaves the old count alone. The target row is locked while this happens so two attempts at once cannot both create one. |
| Retention | Read items 90 days, unread 180 days, configurable (`notifications.retention`), by a scheduled command with `--dry-run`; audit event `notification.purged` (counts only). Opening, reading and creating are not audited. |

## The table

`user_notifications`: `recipient_user_id` (the recipient is a plain user id, so other roles can use the same table later), `type` (dotted key), `target_type` + `target_id`, `label`, `occurrence_key`, `count`, `occurred_at`, `read_at`, timestamps. No JSON.

Indexes: **unique** `(recipient, type, occurrence_key)` (a retried job or a re-run scheduled task cannot create a second one); `(recipient, read_at, occurred_at)` for the bell's count and the lists; `(recipient, type, target_id, created_at)` for coalescing; `(read_at, occurred_at)` for retention.

## What a row may hold

Only: the type key, the target (kind + id), a **short label** (an organization name, a counselor name or a complaint reference such as "#12"), a count and timestamps. The producer API has no parameter that could carry anything else, so **complaint text, escalation text, notes, reasons, document names, emails, KB numbers and chat content cannot get in**. The label is cut to 150 characters and tidied to one line, and is escaped again whenever it is shown. **No URL is ever stored**: the page a notification opens is worked out on the server from the type and the target id.

## The type registry

`App\Support\Inbox\NotificationRegistry` (singleton). Each `NotificationType` declares: its **key**, **group** (Applications, LKM, Complaints, Admins, System), a fixed **title**, the **target kind**, a **route resolver** (target id → URL), an optional **state resolver** ("handled") and an optional **count noun** (coalescing types). The core types are in `CoreNotificationTypes`. A later page (Moderate Feed, another role) calls `register()` with its own types; nothing in the core changes (a test does exactly that).

State resolver: given the ids of many targets, says for each `open` (still waiting), `handled` (already decided: the panel shows "Already reviewed", dimmed) or `gone` (deleted). The registry asks each type **once** for a whole list, so the number of queries depends on the number of kinds in the list, never on the number of items.

| Type | Group | Opens | Handled when |
|---|---|---|---|
| `organization.application_submitted` | Applications | the organization | it is no longer Pending |
| `organization.application_resubmitted` (hook) | Applications | the organization | it is no longer Pending |
| `counselor.application_submitted` | Applications | the counselor | no longer Pending |
| `counselor.application_resubmitted` | Applications | the counselor | no longer Pending |
| `counselor.kb_duplicate_flagged` (coalesces: "3 attempts") | Applications | the counselor | never (a heads-up) |
| `lkm.review_requested` | LKM | Review requests | the request is decided |
| `complaint.received` (hook) | Complaints | the complaint | closed (resolved or dismissed) |
| `complaint.escalated` (hook) | Complaints | the complaint | closed |
| `admin.invitation_accepted` | Admins | Platform Admins | never |
| `counselor.pa_expired_suspended` | System | the counselor | no longer suspended |
| `system.maintenance_auto_reopened` | System | Registration Control | n/a (no target) |

"Hook" = the type is defined and tested with fixtures, but no code produces it yet (no code creates or escalates a complaint, and a rejected organization cannot apply again yet). The two system types have a source that exists (the PA expiry check and the maintenance reopen), and are wired in step 3.

## The service (`NotificationCenter`)

Producing: `publish()` and `publishCoalescing()` (best effort), `notifyAdmins()` and `notifyAdminsCoalescing()` (the same but they throw; used by tests). Reading and changing, **always the signed-in person's own**: `unreadCount()` (one indexed count), `latest()`, `find()`, `markRead()`, `markUnread()`, `markAllRead()` (returns how many it changed), `deleteAllFor()` (used when a Platform Admin is removed). Another admin's notification is simply "not found": every query is scoped to the owner.

## The bell, the panel and the redirect (Step 2)

All routes are under `/platform/notifications`, **Platform Admins only** (anyone else gets 403, a signed-out script gets a JSON 401, never a login page), each **rate limited**, and every one works only on the **signed-in admin's own** notifications.

| Route | What it does |
|---|---|
| `GET /notifications/unread-count` (120/min) | `{"count": n}` for the polling. One indexed count query. |
| `GET /notifications/panel` (60/min) | The panel's contents as HTML built and **escaped on the server**: the latest **8**, grouped **Today** and **Earlier** in the admin's timezone. Header `X-Unread-Count`. |
| `POST /notifications/read-all` (30/min) | Marks all of the admin's own as read; answers `{"marked": n, "count": 0}`. |
| `POST /notifications/{id}/open` (60/min) | Marks that one read and redirects to its target. Not yours, or not there: **404**. The target page comes **only from the type registry**, never from what is stored (a test puts a web address in every stored field and checks the redirect ignores it). If the target no longer exists, the admin lands on `/notifications` with "That item no longer exists." It is a POST (a click is a form button), so it needs the CSRF token and cannot be triggered by a link. |
| `GET /notifications` (60/min) | The full page (below). |
| `POST /notifications/{id}/read` and `/unread` (120/min) | Mark one of your own as read or unread, from the page. Another admin's, or one that does not exist: **404**. They come back to the same tab, kind and page number; only those three values are carried back, each checked. |

**The bell** (`components/platform/notification-bell.blade.php` in the header): the badge shows the number itself, capped at **9+**, and is hidden at zero. The button's accessible name includes the count ("Notifications, 3 unread"; "Notifications, 9+ unread"). On a normal page load **only the count is read** (one query); the **panel is fetched when the bell is opened**. The Dashboard went from 27 to **28** queries for a full load (limit stays 40).

**The panel:** each item shows its title, the label, a relative time (hover for the full time in the admin's timezone from the Account page) and, if its target is no longer waiting, a neutral **"Already reviewed"** and is dimmed (a deleted target says "No longer available"). Unread = a filled dot **and** bold text **and** the words "Unread" for screen readers, so it is never colour alone. "Already reviewed" is resolved for the whole list at once (one query per kind of notification, not per item). Empty: "You're all caught up." Header: "Mark all as read". Footer: "View all notifications".

**Keyboard:** Enter or Space opens (it is a button); Tab stays inside the open panel; items are buttons reachable by Tab; Escape closes it and returns focus to the bell.

**Polling** (`resources/js/notification-bell.js`): the count is refreshed every **60 seconds**, **only while the tab is visible** (a hidden tab does not call the server; coming back asks at once). Errors back off 1, 2, 4, 8 minutes and then at most 15 minutes, and a good answer resets it. **401, 403 and 419 (and a redirect to the sign-in page) stop it for good.** No websockets. The logic is tested without a browser: `npm run test:js` (Node's built-in test runner, no extra packages).

**Not checked in a browser:** the panel's look, the keyboard behaviour and the polling in a real browser have not been seen. Run `npm run dev` or `npm run build` so the new script and styles are included.

## The full page (Step 3)

`/platform/notifications`, reached from the panel's "View all notifications" (**no sidebar entry**).

- Tabs **All** and **Unread** with counts, a filter by kind (**Applications, LKM, Complaints, Admins, System**; the counts follow the filter), **25 per page** with the filters kept in the page links. A page past the end goes to the last real page.
- Per item: open it (marks it read and goes through the registry), **Mark as read / Mark as unread**, and the "Already reviewed" / "No longer available" state.
- **"Mark all as read (N)"** marks all unread, or only those of the kind being looked at ("Mark all in System as read"), and says how many it changed ("Marked 3 notifications as read." / "Nothing to mark: everything is already read."). Disabled when nothing is unread. The bell's own "Mark all" (a script asking for JSON) is every unread one.
- **Three different empty states:** no notifications at all ("No notifications yet"), nothing unread ("You're all caught up"), and no match for the filter ("No notifications match this filter", with a way to clear it).
- A small note states the retention rule with the real periods.

## Who produces notifications (Step 3)

Each producer calls `NotificationCenter::publish()` (or `publishCoalescing()`) **after the commit**, from the **same place** that already sent the email, so the two cannot drift apart. In-app items are created for every active admin; the Account preference decides the **email** only. Neither blocks the action if it fails (the failure is reported).

| Event | Where it is produced | Occurrence (so a retry or re-run adds nothing) |
|---|---|---|
| `organization.application_submitted` | `PlatformAdminNotifier::newOrganizationApplication` (called by `OrgSignupController` after the sign-up commits), together with the email | the application number |
| `counselor.application_submitted` / `_resubmitted` | `PlatformAdminNotifier::newCounselorApplication` (called after the commit by `CounselorDocumentController`), together with the email | the attempt number |
| `counselor.kb_duplicate_flagged` | `CounselorKbAttemptRecorder::record`, after the attempt is saved. **Coalesced:** one per KB number per 24 hours with a count | (coalescing, see above) |
| `lkm.review_requested` | `LkmReviewService::request` | the request itself |
| `admin.invitation_accepted` | `AdminInvitationService::accept`; **everyone but the new admin** is told | the invitation |
| `counselor.pa_expired_suspended` | `CounselorStatusService::suspend`, **only** for the automatic (system) suspension, not an admin's own | the PA date that ran out |
| `system.maintenance_auto_reopened` | `PlatformSettings::reopenIfDue` | the time maintenance was due to end |

**Defined, not produced yet (hooks):** `organization.application_resubmitted` (a rejected organization cannot apply again yet), `complaint.received` and `complaint.escalated` (nothing creates or escalates a complaint yet). When their source exists, it only has to call `publish()`.

**Never notified of their own actions:** the actor is left out (the only admin-caused event is a new admin joining). **A removed admin's notifications are deleted** when they are removed (`PlatformAdminService::remove`), and they receive nothing afterwards.

## Retention (Step 3)

`php artisan notifications:purge` (scheduled daily at 03:20; needs the cron `schedule:run` like the other clean-ups). Read notifications older than **90 days** and unread ones older than **180 days** (counted from when the thing happened) are deleted in batches of 1,000. `--dry-run` only counts. When something was deleted, **one system audit entry `notification.purged`** records the counts (read, unread) and the two periods, never a name or label. A read item marked unread again moves to the longer 180-day rule. The periods: `NOTIFICATIONS_READ_DAYS` and `NOTIFICATIONS_UNREAD_DAYS`.

## Demo data (Step 3)

`php artisan db:seed --class=NotificationDemoSeeder` (also part of `db:seed`; **local and testing only**, it stops with an error anywhere else). For every active Platform Admin: about 35 items across all five groups, read and unread, three already-reviewed items, a duplicate-KB item with "3 attempts" and enough for a second page. The targets are small made-up records ("[Demo] Notifications …", "Demo Notifications …", `@example.test`). It does nothing the second time.

## Known gap: two Account preferences have no sender

The Account page lets an admin switch **`escalated_complaint`** and **`weekly_summary`** on or off, but **nothing in the application sends those emails yet** (only `new_organization_application` and `new_counselor_application` are sent, by `PlatformAdminNotifier`). The switches do nothing today. They are outside this work (the Account page is not changed). They need their own senders: the escalation email when escalation is built, and the weekly summary as a scheduled job.

## The mobile app's notifications (group `activity`)

The same table and `NotificationCenter` also serve the app (owner's decision of 31 October 2026: an in-app panel, **no push for now**; push needs Firebase/APNs at deployment). They have their own group, `activity`, which the Platform Admin bell and page never show, and the API lists only that group.

**Only what happened that the person did not do themselves.** Booking something, cancelling it or leaving an organization is not news to them. Types (`App\Support\Inbox\ClientNotificationTypes`): `request.approved`, `request.declined`, `request.expired` (target: the time request), `booking.cancelled` (target: the booking; the counselor was suspended, or the membership ended), `invitation.received`, `membership.approved`, `membership.declined`, `membership.ended` (target: the organization; ended only when the organization removed them), `feed.removed`, `feed.warned` (no target: the writing is gone).

**What is stored:** the key, a short label (a counselor's or an organization's name), the target's id and the time. No time of a session, no reason, no message, no e-mail. The API turns a booking or a request into its public id; the app writes the sentence from the key (`title` is an English fallback). Every row also has a random public id (`user_notifications.public_id`).

**Who creates them** (always right beside the e-mail that already exists, never able to fail it): the counselor answering a time request (`CounselorAppointmentController`), `requests:expire`, `OrgMemberService` (approve, decline, invite: only if the invited address already belongs to a confirmed account), `MembershipEnding`, `CounselorStatusService::suspend` (sessions cancelled and requests closed), `FeedModerationService` (remove, warn). `NotificationCenter::notify($user, $type, $target, $label, $occurrence)` is the one door; it never throws and the same occurrence twice makes one.

**E-mail is separate and stays as it is:** only the important events are e-mailed (and the booking ones obey the switch in Account). The panel shows more than the e-mails do.

**In the app:** a bell on Home (top right) shows the unread number as a figure (and the tooltip says it in words); it is asked again whenever Home opens and whenever the app comes back to the front, and it never shows an error. The bell opens `/notifications`: newest first, 20 at a time, each sentence written from the type key. An unread one has a dot AND bolder words AND the word "Unread" for screen readers (never colour alone). Tapping one marks it read and opens what it is about (a booking, a time request, Your organization, the Feed; one whose booking was deleted opens the Bookings tab). "Mark all as read" is at the top of the list while anything is unread. Account > Notifications still switches the e-mails only.

**Retention** is the same as for the admins (read after 90 days, unread after 180). Deleting an account deletes its notifications.

**API:** see [API.md](API.md): `GET /notifications`, `GET /notifications/unread-count`, `POST /notifications/{id}/read`, `POST /notifications/read-all`.

## Not built (app notifications)

- Push notifications (Firebase for Android, APNs for iPhone): needed only for notifications while the app is closed.
- A reminder before a session starts (it would need a scheduled job).

## Tests

All in `tests/Feature/Notifications` plus `tests/js`:

- `NotificationCenterTest`: who receives, no duplicates on retry, best effort, the 24-hour coalescing window to the second, only your own, privacy, indexes. `NotificationRegistryTest`: every type, the pages, handled/gone, no N+1.
- `NotificationBellTest`: access, the badge and its name, the lazy panel, Today/Earlier in the admin's timezone, unread not by colour alone, reviewed state, the redirect through the registry only, crafted requests, rate limits, no audit.
- `NotificationPageTest`: tabs, kind filter, 25 per page, the three empty states, mark one/all with the count, only checked values carried back.
- `NotificationProducersTest`: each event on the real sample data: one per active admin, none for the actor or a removed/suspended admin, none on a retry or a re-run of the daily jobs, a failure never blocks the sign-up, email follows the preference while the in-app item does not, nothing private in the table or the panel, a removed admin's inbox deleted.
- `NotificationRetentionTest`: both periods to the second, the dry run, batches, the audit entry (counts only), the schedule. `NotificationDemoSeederTest`.
- `tests/js/notification-bell.test.mjs` (`npm run test:js`): the polling rules.

The row-lock test only runs on MySQL/MariaDB (SQLite has no row locks).
