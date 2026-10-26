# Registration Control (Platform Admin → Settings → Registration Control)

Close sign-ups, switch maintenance mode on, and edit the wording people see. The original spec was not available; the answers below come from the product owner.

## Decisions

- **Sign-ups can be closed per kind of account** (Organizations, Counselors, Individuals) **and all at once** ("Close all / Open all" just sets the three switches; there is no fourth hidden state).
- **Closing stops only NEW accounts.** The first step is guarded (organization sign-up, counselor sign-up, individual `register`). Someone who already started (domain check, document upload, waiting for approval) can finish, and existing accounts are not touched. Members joining an organization with a code or domain are not "sign-ups" and are not affected.
- **Individuals sign up in the mobile app only.** The Individuals switch closes the app's `POST /api/v1/auth/register` (see `docs/API.md`; the app asks `GET /api/v1/status` first to show the closed message). The web sign-up form for individuals is kept for a future web version but is **switched off** (next section).
- **Maintenance mode really blocks.** While on, every web request is answered with a 503 maintenance page (JSON for API-style requests) unless the person is a signed-in Platform Admin working in the Platform Admin role. Still open, so an admin can always get in to switch it off: the sign-in page **and its submit** (`login.store`), two-factor, forgot/reset-password and the role chooser. Right after signing in everybody is sent to `/dashboard` before any role is chosen: for a person who holds an **active Platform Admin role** (and nobody else) that hop is let through too, so the sign-in can finish all the way to the Platform Admin area. Anyone else can still sign in but is stopped at the maintenance page on every page after it; someone who is no longer a Platform Admin is stopped like everybody else. Unknown URLs stay a normal 404. The API is blocked the same way (JSON 503 with the message), except `GET /api/v1/status`. Console/scheduler/queue are not affected. Platform Admins see a yellow bar on every page while it is on.
- **Wording is a template the admin can edit** (plain text, 10–500 characters, shown with line breaks, never as HTML): one message for "sign-up closed" (`{role}` becomes organization / counselor / individual, or "account" when everything is closed) and one for maintenance. "Use the original wording" restores the default (in `config/platform-settings.php`).
- Closing / maintenance ask for a confirmation (no reason needed). Opening is immediate. Every switch sends the state it wants, so a double-click or two admins never flips it back; an already-reached state shows "nothing changed" and writes no audit entry.

## Waitlist

While Organization or Counselor sign-up is closed, people can leave an email address to hear when it reopens; the section is on this page. Individuals have none (they use the app). It has its own document: `WAITLIST.md` (what is stored, the opt-out, "Notify all" rules, retention, the secret `WAITLIST_HMAC_KEY`).

## The individual sign-up form on the web is switched off

Setting: `REGISTRATION_WEB_INDIVIDUAL_ENABLED` in `.env` → `config('registration.web_individual_enabled')` (`config/registration.php`). **Default and production: `false`.** It is `true` only on a developer's own machine (the local `.env`, so test users can be made through the form) and in the tests (`phpunit.xml`).

- **While it is false** the web routes `GET /register` and `POST /register` answer **404**, for signed-in and signed-out visitors alike (the check comes before the "already signed in" redirect; nothing is redirected anywhere). The Blade views, controller and tests of the form are kept; nothing was deleted. The mobile app's own `POST /api/v1/auth/register` is **not** affected by this setting.
- **While it is true** the form works as before **and the Individuals switch in Registration Control still applies** (closed = the "sign-up closed" page, 403).
- **No public link to the form:** the landing page button and the header link now read **"Get the mobile app"** and lead to a placeholder download section on the landing page (`id="get-the-app"`; `MOBILE_APP_URL` in `.env` changes where they lead once the app is in the stores). The Log in page keeps "Sign up" (which opens the account-type chooser with **Organization** and **Registered Counselor** only) and has a "Looking for support? Get the mobile app" line; the chooser has the same line. There was never an Individual card on the chooser, and the Log in page has a link rather than a drop-down; both were kept as they are.
- **Organization and counselor sign-up on the web are unchanged** and obey their own switches.
- **"Continue with Google":** on the web it is only a "coming soon" button today (no OAuth code, no route, Socialite is not installed), so no account can be made through it. **When it is built:** an existing account signing in with Google is not a sign-up and stays allowed; creating a NEW account through Google on the web must first ask `PlatformSettings::canCreateIndividualOnWeb()` (true only when the config setting above is true AND the Individuals switch is open) and refuse otherwise. A test (`WebIndividualRegistrationTest`) fails as soon as Socialite is installed, as a reminder to do this and to replace it with a real test.

## Maintenance with a reopen time

- **Optional "switch off by itself at" time**, set when turning maintenance on (the confirmation window has a date-time field and quick choices: 30 minutes, 1, 2 or 4 hours) or changed / removed later from the Maintenance panel. The admin types their **own** wall-clock time; it is read in the admin's time zone (the page says which, e.g. "Asia/Kuala_Lumpur, UTC+08:00") and kept as a UTC instant (`maintenance.reopen_at`). It must be at least a minute away and at most 30 days ahead (`maintenance_max_days`). Turning maintenance off by hand (or with `platform:reopen`) forgets it, so an old time never fires in a later maintenance.
- **The scheduler turns maintenance off:** `maintenance:reopen-due` runs **every minute** (`routes/console.php`, without overlapping) and switches maintenance off once the time has arrived (at the exact moment counts). It is safe to run any number of times: it acts once and does nothing if maintenance is already off, no time is set, the time has not come, or the admin removed or moved it meanwhile. It is written to the audit log as a **system** action. If it fails (for example the audit entry cannot be written) nothing changes and the next minute tries again.
- **The server's cron must run the scheduler**, or no reopen time will ever fire: `* * * * * cd /path/to/mykaunsel-web && php artisan schedule:run >> /dev/null 2>&1`. If the time has passed and maintenance is still on, the Registration Control page says so in a warning ("the scheduler may not be running"), and you can turn it off by hand any time. (The same cron already runs the daily PA check and the document retention.)
- **What visitors get:** the 503 page says when MyKaunsel is expected back, in `PUBLIC_TIMEZONE` (default Malaysia time) with the zone written out; `Retry-After` is the number of seconds left until then (a default of 10 minutes when there is no time, and 1 minute once the time has passed without being promised). The JSON answer has `reopens_at` (ISO-8601, UTC) and the app's `GET /api/v1/status` has `maintenance.reopens_at`, both `null` when there is no time or it has passed.
- **Not indexed:** the maintenance page and the "sign-up closed" page send `X-Robots-Tag: noindex, nofollow` (and the maintenance page also has a robots meta tag). The message is plain text and shown escaped.
- **Audit names:** kept as `settings.*` (not the `maintenance.*` / `registration.*` names of the original checklist): `settings.maintenance_toggled` (now also carries the reopen time), new `settings.maintenance_reopen_scheduled` (set / changed / removed, old and new instant) and new `settings.maintenance_auto_reopened` (the scheduler, actor "system").

## Audit

`settings.registration_toggled` (role + old/new), `settings.maintenance_toggled`, `settings.maintenance_reopen_scheduled`, `settings.maintenance_auto_reopened`, and `settings.message_updated` (says which message, not its text). Close-all writes one entry per switch that really changed. Every change is saved together with its audit entry in one transaction: if the entry cannot be written, nothing is saved (tested for every action on the page).

## Also fixed here (found while building the maintenance gate)

`POST /select-context` trusted the `org_id` and `role` sent by the browser, so a user who belonged to any organization could pick the role `platform_admin` and open the Platform Admin area. It now checks that the user really has that role in that organization (403 otherwise). Test: `test_a_role_the_user_does_not_have_cannot_be_chosen`.

## Needs

`php artisan migrate` (creates `platform_settings` with everything open and maintenance off, and the reopen-time setting). The server's cron must run `php artisan schedule:run` every minute (see above).

## Locked out? The emergency exit (no browser needed)

If a wrong setting leaves the platform closed (for example maintenance left on, or every sign-up closed) and nobody can get in to change it, run on the server, from the project folder:

```
php artisan platform:reopen              # maintenance OFF and every sign-up OPEN
php artisan platform:reopen --maintenance   # only turn maintenance off
php artisan platform:reopen --registration  # only open the sign-ups
php artisan platform:reopen --dry-run       # show the current state and what would change, change nothing
```

- It needs no sign-in, no browser and no waiting: nothing is cached, so visitors are served on their very next request.
- It is **all or nothing** (one database transaction) and safe to run twice: what is already open is left alone and nothing more is logged. The wording of the messages is never touched.
- Every change is written to the audit log as a **system** action marked `via: command` ("Changed from the command line"), so the history shows it was done from the terminal. The command is not scheduled; it only runs when someone types it.
- If it says the `platform_settings` table is missing, run `php artisan migrate` first.
- Last resort, only if PHP itself cannot run commands: in the database set the rows to JSON `false` / `true`, e.g. `UPDATE platform_settings SET value = 'false' WHERE key = 'maintenance';` and `UPDATE platform_settings SET value = 'true' WHERE key LIKE 'registration.%';` (no audit entry is written that way).

## For developers: adding a sign-in route

Maintenance mode lets routes through **by name** (`config/platform-settings.php` → `maintenance_allowed_routes`). A sign-in route with no name, or one that is not listed, answers 503 during maintenance and can lock every admin out. That already happened once (the sign-in page was open but its submit was not). So: give every route used before someone is signed in a name and list it. The test `MaintenanceAccessTest` fails with the route's address if one is forgotten, and walks the whole sign-in (password, two-factor, role chooser) during maintenance.

