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

**The signed-in app** has three tabs (bottom bar): Home, Find, Account. Each tab keeps its own place while you visit another (`StatefulShellRoute`); tapping the tab you are on returns to its first page.

**Find a counselor** (`features/counselors`; privacy rules in `docs/PUBLIC_COUNSELOR_DATA.md`):

- Search by name (waits 400 ms after typing stops; never searches for a single letter), filter sheet (session type, focus area, language, state: the choices come from what this person can really find, so no filter gives an empty list), endless scrolling (20 at a time), pull to refresh.
- Cards: name, "Verified registered counselor", where they work (independent / organization), focus areas (3 + "+N more"), languages, session types, city and state, next free time ("Today, 2:00 PM" / "Tomorrow, 10:00 AM" / "Thu 5 Nov, 10:30 AM").
- Profile: all focus areas, languages, session types, places (city and state only), "also takes requests", free times grouped by day. "See available times" opens Pick a slot (below).
- **Times are always Malaysian time** (UTC+8, no daylight saving), whatever the phone's clock or zone says: the counselor and an in-person session are in Malaysia. The server sends `+08:00`; `shared/format/slot_time.dart` does the reading.
- **One person's results never reach the next person.** The list watches `currentUserIdProvider` and is `autoDispose`: signing out throws it away, signing in as someone else fetches again, and a slow answer for the previous person is discarded (a member of a closed organization sees counselors a stranger must not).
- A counselor that is hidden from this person and one that does not exist are both "Counselor not available" (404 → `NotFoundFailure`).
- Unknown values from a newer server (a new session type, a new kind of workplace) become `unknown` and are simply not shown: an old app does not crash.

**Pick a slot** (`features/booking`; the server side is `docs/AVAILABILITY.md`):

- Opened from a counselor's page at `/book/:id` (outside the tabs, no bottom bar). The next step is `/book/:id/confirm`, which is still a placeholder that books nothing.
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

## Adding a feature (the recipe)

1. `features/<name>/domain` models (freezed) → `data` API class (use `ApiClient`, throw nothing but `AppFailure`) → `presentation` screens + a `Notifier`.
2. Add the provider to `core/providers.dart` (or inside the feature), the route to `core/router/app_router.dart` (and to `AppRoutes.signedOutPages` if a signed-out person may open it).
3. Strings go in `app_en.arb`, not in widgets.
4. Tests: API class against `FakeHttpAdapter`, notifier with fakes, one widget test for the main flow.
5. The matching endpoint rules are in `docs/API.md`.
