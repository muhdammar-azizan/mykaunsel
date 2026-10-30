# Mobile app architecture (Flutter, `mykaunsel-app/`)

Decisions are final unless this file says otherwise. Why each one was chosen is written next to it, so it is not undone by accident later.

## Targets

- **Individuals first** (clients). Counselor / organization features in the app come later.
- **Android 7.0+ (API 24) and iOS 13+.** Code is written for both; Android is tested first (iOS needs a Mac to build).
- **English first**, with the structure ready for Bahasa Melayu (`lib/l10n/*.arb`; add `app_ms.arb` and translate, no code change).
- **App id `my.com.mykaunsel.app`** (from the domain `mykaunsel.com.my`, reversed). It can **never change** once published. The dev flavor is `my.com.mykaunsel.app.dev`, installable beside the real one.

## Stack (and why)

| Need | Choice | Why |
|---|---|---|
| State | `flutter_riverpod` 3 (`Notifier`) | Testable without widgets; one session shared by the whole app. No code generation for providers. |
| Navigation | `go_router` | Maintained by the Flutter team. One `redirect` decides every screen from the session state. |
| HTTP | `dio` | One client, timeouts, headers in one place. |
| Models | `freezed` + `json_serializable` | Immutable models, safe parsing. Run `dart run build_runner build` after changing a model (generated files are committed). |
| Token | `flutter_secure_storage` | Android Keystore / iOS Keychain. |
| Text | `flutter_localizations` + gen-l10n | Built into Flutter. |
| Version | `package_info_plus` | The server needs the real app version (force-update). |
| Tests | `flutter_test` + `mocktail` | No code generation. |
| Crash reports | **Firebase Crashlytics (decided, not wired yet)** | Needs the Firebase project files. The app already routes every uncaught error through `CrashReporter` (`core/error/crash_reporter.dart`); switching on Crashlytics = one new class + one line in `main.dart`. |

Deliberately **not** used now: certificate pinning (a server certificate change would lock every installed app out), an offline database (nothing to read offline yet), a use-case layer (more files, no benefit at this size).

## Folders

```
lib/
  main.dart, app.dart        start-up wiring, MaterialApp.router
  core/                      shared by every feature
    config/                  AppConfig (flavor + server address, from --dart-define), AppInfo (version, platform)
    error/                   AppFailure (the ONLY error types screens see), CrashReporter
    network/                 ApiClient (dio), failure_mapper (HTTP status → AppFailure)
    storage/                 TokenStorage (secure)
    session/                 SessionState + SessionController: where is this person right now?
    router/                  routes + sessionRedirect (pure function, unit-tested)
    theme/                   colours (same as the web), ThemeData
    providers.dart           wiring of the above
  features/<name>/           data/ (API calls)  domain/ (models)  presentation/ (screens, notifiers)
  shared/widgets/            widgets used by several features
  l10n/                      app_en.arb (+ generated AppLocalizations)
```

A feature never imports another feature's `data/` or `presentation/`; sharing goes through `core/` or `shared/`.

## How the app decides what to show (the session)

`SessionController` is the single answer to "where is this person?":

`starting → maintenance | updateRequired | startupFailed | signedOut | signedIn`

On start it asks the server in this order: **maintenance?** → **is this app too old?** (`GET /status`) → **is there a saved token, and does it still work?** (`GET /auth/me`). The router only reads this state, so nobody can end up on a screen that does not fit their situation. Later, any API call that fails with maintenance (503), update needed (426) or a dead token (401 while signed in) reaches the controller through the API client, so **no screen handles those itself**.

## Errors

The API client turns every failed call into one `AppFailure` (`core/error/app_failure.dart`): `NetworkFailure`, `UnauthorizedFailure`, `EmailNotVerifiedFailure`, `ForbiddenFailure`, `ValidationFailure` (with per-field messages), `UpdateRequiredFailure`, `RateLimitedFailure`, `MaintenanceFailure`, `ServerFailure`. Screens only `catch (AppFailure)` and show the message. Messages written by the Platform Admin (maintenance, closed sign-up) are shown as the server sent them.

## Real-world rules already built in

1. **Force update.** Every request sends `X-App-Version` and `X-App-Platform`. The server answers 426 once `API_MIN_APP_VERSION` is raised; the app shows an update screen with the store link. This must exist from the very first release.
2. **Token safety.** iOS keeps Keychain data after the app is deleted, so a marker in ordinary preferences detects a fresh install and wipes it. Android data that can no longer be decrypted is wiped and the app carries on signed out instead of crashing. Android backup and device transfer are switched off (the Keystore key would not be restored).
3. **No plain http in production.** `AppConfig` refuses a prod build with an `http` address; only the dev flavor allows cleartext (`android/app/src/dev/AndroidManifest.xml`).
4. **Shared mobile IPs.** The server limits by email / challenge / person, not just IP, so many phones behind one mobile-network IP are not blocked together.
5. **Never log secrets.** The API client logs nothing. Crash reports carry only the error and stack, never names, emails, typed text, bodies or tokens.
6. **Timeouts:** 15 s to connect, 30 s to send / receive; a timeout is a `NetworkFailure` with "try again".
7. **Sign-out works offline.** The token is always removed from the phone; if the server could not be told it simply expires (30 days).
8. **Fonts are bundled** (Plus Jakarta Sans, SIL OFL) so the first launch looks right without a connection.

## Environments

Settings come from `--dart-define-from-file` (never edited in code):

```
flutter run --flavor dev --dart-define-from-file=env/dev.json
flutter build appbundle --flavor prod --dart-define-from-file=env/prod.json   # copy env/prod.example.json → env/prod.json first
```

`env/dev.json` uses `http://10.0.2.2:8000` (the Android emulator's address for the laptop's `php artisan serve`). A real phone needs the laptop's network address instead. `env/prod.json` is not committed.

## Icon and splash

- **App icon:** the heart-and-handshake on the cream background. Android has an adaptive icon (works with round / squircle shapes) and a one-colour **themed icon** for Android 13+; iOS has the full set. The **dev** app has a red **DEV** tag and a pink background, so it is never mistaken for the real one on the same phone.
- **Splash:** the phone's own screen while the app starts: the logo on the same cream as the app's first screen, at the same size, so nothing jumps when Flutter takes over. Android 12+ uses its own splash from `assets/splash/splash_android12.png`.
- The pictures for both come from one script, and are NOT shipped inside the app (only the generated files are):

```
python tool/generate_brand_assets.py        # pictures in assets/icon and assets/splash (needs: pip install pillow)
python tool/generate_icons.py               # Android + iOS icons (real app) and the Android dev icon
dart run flutter_native_splash:create       # the splash for Android and iOS
```

- Config: `flutter_launcher_icons.yaml` (real), `tool/flutter_launcher_icons-dev.yaml` (copied in by the script, because the icon tool ignores the main file when a flavor file sits in the project folder), `flutter_native_splash.yaml`.
- iOS has one icon for both flavors (iOS flavors are not set up yet).

## Manual check on the emulator: My bookings, Cancel, Reschedule, Mode B, Chat

**Nothing in this list has been viewed on a screen yet.** Everything below passes its automated tests (server and app); this is the first look with eyes. Setup, once: `php artisan migrate`, `php artisan serve --host=0.0.0.0`, `php artisan queue:work` (otherwise no e-mail), `php artisan schedule:work` (for the expiry, idle-end and purge jobs), `MAIL_MAILER=log`, seed `AvailabilityDemoSeeder`, and put the Anthropic key in the server's `.env` (never in the app). The Android emulator reaches the laptop at `http://10.0.2.2:8000`. For the counselor side use the web portal as a seeded counselor (`/counselor/appointments`, `/counselor/planner`).

**My bookings and details**
1. Book a time (see the Confirm list above), open Bookings: it is under Upcoming with a "Confirmed" word and icon; a session within the hour says "Starting soon". Pull to refresh. The Past tab is empty with a plain message.
2. Open it: Date, Time, Duration, Type and Reference are read as separate items. An online session says the link appears 15 minutes before. As the counselor, paste a meeting link on `/counselor/appointments`; inside the 15 minutes the app shows **Join session** by itself (no refresh). An in-person session shows the full address and **Get directions**.

**Cancel**
3. **Cancel booking** opens the sheet: what will happen and until when. "Keep booking" asks nothing. Confirm: "Cancelling…", the sheet cannot be closed (not by Back either), then "Booking cancelled." and the booking is under Past as Cancelled. The counselor gets one e-mail in the log; the time is bookable again from another account.
4. A session inside the cancellation window: the sheet is the plain explanation with one OK button. Airplane mode while cancelling: the words, then **Try again** works after reconnecting.

**Reschedule**
5. **Reschedule** (with "You can reschedule this booking 2 more times.") opens "Choose a new time" for the same counselor. Pick a time, Continue: "Move to this time?" shows the old and the new time. Move: back on the booking at the new time, "Booking rescheduled."; the old time is bookable again and the counselor gets one e-mail with both times.
6. Move twice: the button is replaced by "…rescheduled the most times it can be." Pick a time another account just booked: "That time was just taken…" and the picker marks it. A time that overlaps another session of yours: "You already have a session at this time."

**Counselor portal (web)**
7. `/counselor/planner`: add a weekly line (for example Thursday 10:00 to 13:00, one-hour times, online) and press "Open the next weeks": the times appear below and are bookable from the app. A time that overlaps one already open, a booked one or a personal block is skipped and counted. Press it again: nothing opens twice. Try to remove a time that has ever been booked: it is refused.
8. `/counselor/appointments`: "Needs marking" lists ended sessions; Mark completed / no-show; the app shows it under Past. A link must start with `https://`.

**Ask for a time (Mode B)**
9. On a counselor's page that takes requests, **Ask for a different time**: the days and times come from their weekly lines; pick one, add a note, **Ask for …**. The request opens as "Waiting for an answer"; the Requests tab (third tab of Bookings) lists it. The counselor gets an e-mail.
10. As the counselor, Approve on `/counselor/appointments` ("Time requests"): the app shows Approved and **View booking** opens an ordinary booking (cancel and reschedule work). Decline with a reason: the app shows "Declined" and the counselor's message, and **Choose another time**. Withdraw a waiting request: the sheet, then "Withdrawn".
11. Make the second request overlap your own session, or ask for more than three: the words, nothing created. Run `php artisan requests:expire` after setting `expires_at` in the past: the request becomes Expired and the person gets one e-mail.

**Chat**
12. Sign up a new account: the sign-up form has the agreement tick; "What this means" shows the four lines and saves nothing; Create account is refused without the tick. A person who signed up before has no agreement: the Chat tab shows "Before you chat"; "I agree" opens the conversation; Account shows the "AI chat" switch on; turning it off asks first, and the Chat tab asks again.
13. Say hello: "Thinking…", then a short, kind reply in your language. Say something stressful in Malay and in English. Leave the tab and come back: it is still there. **New conversation** clears it; Chat history lists it (after the first minute it is "Today") and it opens read only. Quiet for an hour with the scheduler on: the conversation ends and shows how you mostly felt.
14. **Worrying words** (try only on a test account, for example "saya rasa tak nak hidup lagi"): the amber card appears under the reply with "Call HEAL 15555" and "Call Talian Kasih 15999"; a tap opens the phone's dialler; the chat still works; **Continue chatting** puts the card away. **Need help now?** (app bar and Home) opens the same lines. Turn off the server: the lines are still there.
15. Remove the key from `.env` (or go offline): the message stays, "I can't reply right now…" with **Try again**. Offline then online: **Send again** sends it once (check the database: one message).
16. Say "I would like to see a counselor": up to three counselor cards appear in the chat and one opens that counselor's page.
17. TalkBack: each bubble reads "You said: …" or "Companion said: …"; the card is announced; the call buttons read "Call HEAL 15555". Largest font and display size: nothing is cut off, the box and the Send button stay reachable.

## Release checklist (not done yet)

- Real upload keystore in `android/key.properties` (see `android/app/build.gradle.kts`; until then release builds are debug-signed and cannot go to Play).
- Android: accept SDK licences (`flutter doctor --android-licenses`).
- iOS: needs a Mac + Apple developer account. The bundle id is already `my.com.mykaunsel.app`; **iOS dev/prod flavors (Xcode schemes) are not set up** and must be added on the Mac.
- Firebase project → `google-services.json` / `GoogleService-Info.plist` → Crashlytics (and later push notifications).
- **A sharp logo.** The icon and splash are made from `assets/images/mykaunsel-icon.png`, which is only 275 × 240 pixels, so they are enlarged (smoothly, but a little soft on very large icons). Give a high-resolution PNG or an SVG of the heart-and-handshake, replace `SOURCE` in `tool/generate_brand_assets.py` and run the commands in "Icon and splash" below. Nothing else changes.
- Real prod server address, store links for `APP_STORE_URL_*` on the server.
- Privacy policy and data-safety forms (needed by both stores; this is a counselling app).

## What is built

**Startup shell:** splash, maintenance, forced update, "no connection / server problem" with retry.

**Account (phases A and B):**

| Screen | Notes |
|---|---|
| Sign in | email + password; field errors from the server; rate-limit message with the wait; offline message; password-manager autofill |
| Two-step code | 6-digit code or a recovery code; an expired challenge sends the person back to sign in |
| Check your email | resend with a 60 s wait (and the server's own wait when it limits); reached after sign-up or after signing in with an unconfirmed address |
| Sign up | asks the server first whether individual sign-up is open and shows the Platform Admin's message when closed; live password checklist (same rules as the server); name / email / password / confirm |
| Forgot password | same answer for every address; the reset page itself opens in the browser |
| Home | greeting and the way to "Find a counselor" |
| Account tab | who is signed in; Profile, Change password, Signed-in devices; sign out, sign out of all devices (asks first) |

How the pages are protected: the two-step page and "check your email" page can only be opened right after the step before them (`pendingRedirect`); a signed-in person can never sit on a sign-in / sign-up page (`sessionRedirect`).

Sign-up, forgot-password are opened with `push` (the back button returns to sign-in); the steps of one flow use `go` (no way back into a half-finished step).

Accessibility already covered: every field has a label, headings are marked, errors are announced, the password checklist reads "…, done / not yet" (not only colour), tap targets are 48 px, pages scroll (tested at text size ×2 on a small screen).

**Account settings** (`features/account`; API in `docs/API.md` "Account"):

- **Profile:** name and time zone (the email is shown but changed on the website, which has the confirmation step). "Save" stays off until something changes; a time zone set on the web that the app does not list is kept, not replaced. Saving updates the name everywhere in the app at once (`SessionController.updateUser`).
- **Change password:** current + new twice, with the same live checklist as sign-up. The server signs every OTHER phone and web session out; this phone stays signed in.
- **Signed-in devices:** this phone first ("This phone"), the others with "Last used 5 hours ago" and a Sign out button that asks for the password in a dialog (wrong password, rate limit and "no connection" are explained inside the dialog; a phone already gone just disappears).
- Anything that checks the password is limited by the server to 5 tries a minute and 20 an hour, so a stolen token cannot be used to guess it.
- Not built here: changing the email, two-step verification setup, deleting the account (needs a data policy and a backend fix first).

**The signed-in app** has four tabs (bottom bar): Home, Find, Bookings, Account. Each tab keeps its own place while you visit another (`StatefulShellRoute`); tapping the tab you are on returns to its first page.

**Find a counselor** (`features/counselors`; privacy rules in `docs/PUBLIC_COUNSELOR_DATA.md`):

- Search by name (waits 400 ms after typing stops; never searches for a single letter), filter sheet (session type, focus area, language, state: the choices come from what this person can really find, so no filter gives an empty list), endless scrolling (20 at a time), pull to refresh.
- Cards: name, "Verified registered counselor", where they work (independent / organization), focus areas (3 + "+N more"), languages, session types, city and state, next free time ("Today, 2:00 PM" / "Tomorrow, 10:00 AM" / "Thu 5 Nov, 10:30 AM").
- Profile: all focus areas, languages, session types, places (city and state only), "also takes requests", free times grouped by day. "See available times" opens Pick a slot (below).
- **Times are always Malaysian time** (UTC+8, no daylight saving), whatever the phone's clock or zone says: the counselor and an in-person session are in Malaysia. The server sends `+08:00`; `shared/format/slot_time.dart` does the reading.
- **One person's results never reach the next person.** The list watches `currentUserIdProvider` and is `autoDispose`: signing out throws it away, signing in as someone else fetches again, and a slow answer for the previous person is discarded (a member of a closed organization sees counselors a stranger must not).
- A counselor that is hidden from this person and one that does not exist are both "Counselor not available" (404 → `NotFoundFailure`).
- Unknown values from a newer server (a new session type, a new kind of workplace) become `unknown` and are simply not shown: an old app does not crash.

**Pick a slot** (`features/booking`; the server side is `docs/AVAILABILITY.md`):

- Opened from a counselor's page at `/book/:id` (outside the tabs, no bottom bar). The next step is Confirm booking (below).
- Mode toggle (only when the counselor offers both; the mode is filtered in the app, never sent to the server), a strip of dates (14 days per request; "Show later dates" until the 30-day horizon), and the day's times. Nothing is picked at the start; one time at a time; a booked time is dimmed, struck through, says "Booked" and cannot be tapped (never colour alone: the picked time also has a check mark).
- A refresh (pull down) that finds the picked time gone drops the pick and says "Your selected time is no longer available." A failed refresh keeps what is on screen and says so. After "too many requests" nothing is asked again until the wait is over.
- Every "no" (hidden, suspended, not accepting bookings) is the same 404, shown as "Counselor not available". An unverified email shows "Verify my email": it signs out and opens "check your email" (a signed-in person cannot open that page).
- The pure rules (first mode and date, what a tap clears, refresh reconciliation) are in `pick_slot_state.dart` and tested alone; the screen is `pick_slot_screen.dart`.
- Tokens: the new screens take spacing, sizes and booking colours from `core/theme/app_tokens.dart` and the theme's `ColorScheme`, never a hex.
- **Known debt:** 10 places in older screens still use `Colors.white` / `Colors.green` / `Colors.grey` directly (spinners on buttons in sign-in, sign-up, two-step, forgot-password, profile and change-password; the cards in devices, counselor card and home; the password checklist). Move them to the theme when those screens are next touched.

**Manual check on the emulator (Pick a slot)** — nothing here has been viewed on a screen yet:

1. Run the server (`php artisan serve --host=0.0.0.0`) and seed the demo (`AvailabilityDemoSeeder`); the Android emulator reaches it at `http://10.0.2.2:8000`.
2. Sign in, Find, open a counselor, "See available times". Check the skeleton, the nearest date is selected, nothing is picked, Continue is off.
3. Pick a time: Continue shows the day and time. Pick another: the first is released. Change the date or the mode: the pick clears.
4. A counselor with both modes: the toggle appears; a mode with nothing that day moves to the nearest day that has something.
5. Pull down to refresh; then book that time from the web or another account and refresh again: the notice appears and the pick is gone.
6. Switch off the network: "Try again" on first load; "Could not refresh" with the old times kept after a refresh.
7. Phone zone not UTC+8: the "Times are in Malaysian time" note shows; times still read as the counselor's clock.
8. TalkBack: a date reads "Thursday 5 November, 1 time"; a time reads "10:00 AM, 60 minutes, Online"; a booked one adds "Booked"; the picked one adds "Selected".
9. Settings → Display size / Font size at the largest: nothing is cut off and Continue stays reachable.

**Confirm booking** (`features/booking`; the server side is `docs/AVAILABILITY.md`, "The confirm step"):

- Opened from Pick a slot at `/book/:id/confirm?slot=<slot public id>`. The slot id is in the address so the screen can **restore itself from the two ids alone** (after a sign-in); the snapshot from the picker (`ConfirmBookingArgs`) only lets something show at once. Opened by address, the picker is built underneath, so Back works.
- On open it shows the snapshot, then calls the **preview** (claims nothing). Valid: the rules appear. The time is gone (`slot_taken`): back to the picker, which dims the time and says "That time was just taken. Please choose another." (a signal, `slot_taken_signal.dart`, because the picker may not have opened this screen itself). Details differ from what was shown: the summary follows the server, a tick already given is taken back and the banner says so.
- Summary card (avatar initials, name, Verified, Date, Time "9:00 AM – 9:50 AM, Malaysia time", Duration, Type), "Choose a different time", "Before you confirm" (cancel hours, reschedules, meeting-link wait or the address rule: **every number from the server**; inside the cancellation window the warning variant, with an icon), and "You have 2 of 3 upcoming sessions." only when the server sends it. No price anywhere.
- **The tick and the button are together in a sticky bar at the bottom**, so the reason the button is off is always on screen. The button is on only when the box is ticked AND the check is good AND nothing is wrong. While submitting: "Confirming…", the tick is locked and Back (the bar's and the phone's) does nothing.
- **Idempotency-Key:** a version 4 UUID made when the screen opens, **in memory only**. Reused for automatic retries (timeout, no connection, 5xx, 409 `request_in_progress`; up to 3 retries with 1, 2, 4 s). Replaced only after a definite refusal (4xx), so the next tap is a new request. Never stored, printed or logged (a test reads the sources to prove it).
- **Unknown outcome** (no answer after the retries): "We couldn't confirm your booking. Check My Bookings before trying again." with a button to Home (My Bookings does not exist yet). **Never assumed failed**; the booking button stays off. When the app comes back to the front, the same key is sent once more to resolve it. A 5xx that does not clear shows "Try again" with the same key.
- Success replaces everything with "Booking confirmed" (`features/bookings/presentation/booking_confirmed_screen.dart`; Back goes Home).
- Every refusal has its words: `time_conflict`, `upcoming_limit_reached` (with the server's limit), `email_not_verified` ("Verify my email"), 404 ("Find another counselor"), 429 (with the wait), 422 (the server's words), 503 (the global maintenance screen).
- **401:** the person goes to sign-in and, after signing in (and the code step), comes back to this screen; **nothing is resent**, they tap Confirm again. The return destination (`returnPathProvider`) is in memory only and accepted only if it is exactly `/book/<id>` or `/book/<id>/confirm?slot=<id>` (`isSafeReturnPath`: no scheme, no host, no `..`, no other query), so it cannot be an external address.
- Accessibility: each row announced as its own item ("Date: Tuesday, 3 November 2026"); the tick is one checkbox whose whole row taps and whose state is announced; the off button says why ("Checking availability", "No internet connection", "Tick the box to continue"); banners and errors are live regions and focus goes to a new problem; warnings are words + an icon (+ a spoken "Important"); targets at least 44 px, 8 apart; tested at text size ×2 on a 320 px screen. **Found by the tests:** a hint on a wrapper around the button did not reach the button's own item, so a screen reader would not have said why it was off; the wrapper is now merged with the button.
- Tests: `confirm_booking_flow_test.dart` (states, key rules, outcomes, every refusal, 401 and return), `confirm_booking_a11y_test.dart` (semantics, targets, no colours or sentences in widgets, nothing stored or logged), `booking_api_test.dart`, `return_path_test.dart`.

**Manual check on the emulator (Confirm booking).** Nothing here has been viewed on a screen yet. The Android emulator reaches the laptop at `http://10.0.2.2:8000` (not `localhost`); run the server with `php artisan serve --host=0.0.0.0`, the queue worker with `php artisan queue:work` (otherwise no e-mail), and seed `AvailabilityDemoSeeder`. Demo people: `demo.availability.atcap` (at the limit), `.overlap` (an overlapping session), `.member`, `.nonmember`, `.unverified`; the counselors "Demo Booking Lab One" (target) and "Two".

1. Pick a time, Continue: the card shows at once, "Checking availability…" disappears, the rules appear. The tick and the button sit together at the bottom; the button is off until the tick.
2. Tick, Confirm: "Confirming…", then "Booking confirmed" (placeholder). Back goes Home, never to the confirm screen. Check the two e-mails in the log (`MAIL_MAILER=log`): the client's has no link and no address; the counselor's has the client's name and the meeting-link reminder.
3. Book the same time from another account between preview and Confirm: back at the picker with "That time was just taken…" and the time dimmed.
4. Change the slot's length in the database between the preview and Confirm: "Details changed…", the tick is cleared, the new time shows.
5. As `atcap`: the limit message. As `overlap`: "You already have a session at this time."
6. The slot "in 5 hours": the warning "can't be cancelled after booking" with an icon.
7. Airplane mode on open: the snapshot stays, "No internet connection", the button is off. Switch it on, "Check again". Airplane mode right after tapping Confirm: it retries; if it never answers you see "We couldn't confirm…", not "failed". Come back online and bring the app to the front: it resolves.
8. Sign in as `unverified`: "Verify your email…" and the button works. Delete the token on the server (or wait out the session): you land on sign-in, and after signing in (and the code) you are back on this screen; nothing was resent.
9. TalkBack: Date/Time/Duration/Type are read as separate items; the tick reads "checked/not checked" and the whole row toggles; the off button says why; the banner is announced; warnings start with "Important".
10. Largest font and display size: nothing is cut off, the bottom bar stays usable, the list scrolls.

**Bookings** (`features/bookings`; the server side is `docs/AVAILABILITY.md`, "My bookings"):

- The **Bookings tab** has Upcoming and Past. A card says when (Today / Tomorrow / date, Malaysian time), who, in person or online, and its state as a **word and an icon** (Confirmed, Completed, Cancelled, No-show; "Starting soon" when the server says so). Past has "Book again" (opens that counselor's page; hidden when the counselor no longer has one). Pull to refresh; Past loads 20 at a time and also asks for the next page by itself when a page is too short to scroll; empty states and "Try again". The list is asked for again after a booking is made.
- **Booking details** (`/bookings/:id`, inside the Bookings tab): state, the facts as separate labelled items, the **reference** (the public id, never a counter), and **Session details**: online shows when the link will appear, "Join session" (only when the server sent a link, i.e. inside its window), "not added yet" or "ended"; in person shows the full address and "Get directions" (a map search by coordinates, or by the address). The screen **asks the server again at the moment the window opens or closes** (a timer) and when the app returns to the front. It never works the window out itself. A link is opened only if it is an http(s) address; anything else is refused with a message. A cancelled booking shows neither link nor address. A line says until when it can be cancelled, and **Cancel booking** opens the sheet below.
- **Cancel booking** (`cancel_booking_sheet.dart`, `cancel_booking_controller.dart`): a bottom sheet with two states decided by the server. Allowed: what will happen ("Your session with … will be cancelled and the time will be released."), until when, "Cancel booking" and "Keep booking". Too close to the start: the same plain explanation as on the confirm screen ("starts in under N hours, so it can't be cancelled after booking") and one OK button. While it works the sheet cannot be closed (not by the phone's Back either). **Cancelling twice changes nothing more**, so any answer that does not arrive is simply asked again: a lost answer shows the words and the button turns into "Try again". If the server says it is closed now, the sheet turns into the explanation and the page refreshes. After a cancel the booking page and both lists are asked for again, and a note says "Booking cancelled."
- **Reschedule** (`reschedule_booking_sheet.dart`, `reschedule_booking_controller.dart`, `reschedule_args.dart`): on the booking page, when the server says `can_reschedule`, a **Reschedule** button (with "You can reschedule this booking N more times.") opens the SAME time picker for the same counselor, titled "Choose a new time". The booking and its current time travel in the route's `extra`; without them (an old link) the route goes back to the booking. Picking a time and Continue opens the sheet "Move to this time?" ("…will move from X to Y. The time you have now will be released."), with "Move booking" and "Keep current time". When the server says no, the page says why instead of the button (too close to the start, or no moves left). In the sheet: a time that was taken or edited meanwhile says so and "Choose another time" marks it (or reloads the times) and keeps the person on the picker; a clash with another session says so; closed, no moves left, over or gone turn it into a plain explanation with OK, back on a refreshed booking. While it works the sheet cannot be closed. **Asking for the same time twice changes nothing more**, so a lost answer shows the words and the button turns into "Try again". After a move the booking page and both lists are asked for again, and a note says "Booking rescheduled." Tests: `reschedule_booking_test.dart`, three in `my_bookings_api_test.dart`.
- **Chat** (`features/chat/`; the server side is in [CHAT.md](CHAT.md)): the fifth tab (Home, Find, **Chat**, Bookings, Account). **The app only ever calls the server; the server calls the model.**
  - **Consent first:** a person who has not agreed to AI processing sees "Before you chat" (four plain lines: it is an AI, what is sent and not sent, kept 12 months and unreadable to counselors and organizations, can be turned off in Account) with **I agree** and **Not now**. Nothing is sent or stored before. The same words are asked when the person turns the chat on in Account. **Account has an "AI chat" switch**: turning it off asks first and tells them the history stays; the switch follows the server's answer, never ahead of it; if the server does not answer it stays as it was.
  - **The conversation** (`chat_controller.dart`, kept while the person is signed in, so leaving the tab keeps it): a greeting by first name, bubbles, a box (1000 characters, the count shows near the limit), "AI companion. Not a counselor or a doctor." under it. A message carries a **client id made once per message**: "Send again" after a lost answer sends the same message with the same id, so it is stored once. Failures are shown on the person's own bubble in words, with "Send again" (no connection; a server error says it may or may not have been sent and will not be sent twice; too many messages says how long to wait; the daily limit says come back tomorrow; a full conversation says to start a new one). If the companion can't answer, the message stays, a kind line says so, and **Try again** asks for the answer to the conversation (the message is never sent again). If the server ended the conversation meanwhile (quiet for an hour), the message starts a new one and says so. If consent was withdrawn elsewhere, the chat asks again.
  - **Worrying words:** the SERVER decides (never the app, never the model alone). The reply carries `crisis` and the phone lines, and the app shows a calm amber card ("You are not alone", **Call HEAL 15555**, **Call Talian Kasih 15999**, the nearest-emergency-department line, **Find a counselor**, **Continue chatting**). The card also shows when the companion could not answer, and it never blocks the chat. **"Need help now?" in the app bar is always there** and opens the same lines; the lines come from the server and, if it can't be reached, are the same ones built into the app (`fallbackHelplines`; **confirm every number before launch**). Calls open `tel:`; if the phone can't, a note gives the number to dial.
  - **Counselors:** when the server offers them (a flagged message, or the person asked for one), up to three of the same cards as search appear in the chat, and a tap opens the counselor's page.
  - **New conversation** (app bar, once there is something to start over from) clears the screen and ends the old one on the server as well as it can; if that does not get through nothing is shown, because the server ends quiet conversations by itself.
  - **History** (clock icon): conversations grouped Today / This week / Earlier (Malaysian time), each with a short preview and how the person mostly felt once it ended; a conversation opens **read only** (no box, no delete). Paged, with "Try again".
  - Tests: `chat_flow_test.dart` (45): consent, talking, the companion down, every failure, worrying words, history, the Account switch, five tabs, 320×640 at ×2.0. Not done: the consent tick at registration, a Chat card on Home, resuming an old conversation after the app was closed, and streaming the answer.
- **Ask for a time** (Mode B; `features/requests/`): on a counselor's page, when they take requests, **Ask for a different time** opens `/request/:id`. It loads the counselor's weekly hours (`GET /counselors/{id}/working-hours`), shows the intro ("… has 48 hours to answer, and nothing is booked until they approve"), the days that have something to ask for (from today to the last bookable date, Malaysian time) and, for the chosen day, every start that fits wholly inside a line, stepping by that line's length, with its length, type and place. Which starts exist is worked out in `request_times.dart` (pure, tested); **the server decides in the end** (it knows the counselor's calendar, the app does not). Then an optional note (500 characters) and one button, **Ask for <day, time>**. The server's answers: the time is not available (the pick is cleared), a clash with the person's own session, too many waiting, the counselor stopped taking requests, the time is outside the hours, the counselor can't be seen, email not confirmed (offers to verify), too many attempts (waits). **Asking again for the same time is safe** (the server answers the request that exists), so a lost answer turns the button into "Try again". On success it opens the request.
- **Requests** (third tab of Bookings, `bookings_screen.dart`) and **the request page** (`/bookings/requests/:id`, `request_detail_screen.dart`): every state is a word and an icon (Waiting for an answer, Approved, Declined, Expired, Withdrawn). Waiting: until when the counselor can answer, and **Withdraw request** (a sheet, locked while it works, safe to repeat; if it was answered meanwhile the sheet says so and the page refreshes). Approved: **View booking**. Declined: the counselor's message if they wrote one. Declined, expired or withdrawn: **Choose another time**. After asking or withdrawing the list is asked for again. Tests: `requests_flow_test.dart`, `request_times_test.dart`, `requests_api_test.dart`.
- Tests: `bookings_flow_test.dart`, `cancel_booking_test.dart`, `my_bookings_api_test.dart`.
- Opening `/bookings/<id>` by address works on its own (the details are fetched by id).

## Adding a feature (the recipe)

1. `features/<name>/domain` models (freezed) → `data` API class (use `ApiClient`, throw nothing but `AppFailure`) → `presentation` screens + a `Notifier`.
2. Add the provider to `core/providers.dart` (or inside the feature), the route to `core/router/app_router.dart` (and to `AppRoutes.signedOutPages` if a signed-out person may open it).
3. Strings go in `app_en.arb`, not in widgets.
4. Tests: API class against `FakeHttpAdapter`, notifier with fakes, one widget test for the main flow.
5. The matching endpoint rules are in `docs/API.md`.
