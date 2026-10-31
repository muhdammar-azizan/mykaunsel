# Mobile app: the full screen list (the plan, with status)

This is the master list the app is built from, one screen at a time. It was written by the project owner (with Claude chat) and pasted into the session on 2026-10-30; the **Details** column is their specification for each screen, kept in their words (translated headings only). The **status** column is kept up to date by whoever builds a screen. A line marked **(check)** is where the built screen is known or suspected to differ from the Details: cross-check these when the spec is reviewed again.

Status: ✅ built · ⚠️ partly built or a placeholder · ❌ not built.

## Rules for every screen (never repeated per screen)

- Central theme tokens, and every string through the ARB files (English for now). No hard-coded colour or text.
- Time is always Malaysia (Asia/Kuala_Lumpur). The screen says "In-person"; the API says `physical`.
- The same general errors on every screen: 401 goes to sign-in and comes back (an in-app route only, never an external URL), 503 goes to the maintenance screen, 429 respects Retry-After, offline shows Retry.
- A counselor the person may not see is the same `404 counselor_unavailable` for every reason.
- No fee, price or payment anywhere.
- Touch targets 44 px, screen-reader labels, never colour alone.
- The server enforces every rule. The app is not trusted.

**Bottom navigation (target: Home, Chat, Bookings, Feed, Profile).** **Now 5 tabs: Home, Chat, Bookings, Feed, Account.** Find has no tab any more: it opens from the Home card and from the help cards (while there, Home stays lit).

## 1. Auth and start

| | Screen | Details |
|---|---|---|
| ✅ | ★ Splash | Check the saved token and the maintenance status. Go to Home or Log in. |
| ✅ | ★ Onboarding | **Built.** 3 short screens (Talk it through, Find a counselor, Feel less alone), shown once before the first sign-in, with Skip and Next/Get started. The "already seen" marker is kept in ordinary preferences (a reinstall shows it again). A signed-in person never sees it. Original spec: 3 short screens, first time only, can be skipped. |
| ✅ | Log in | Email and password, Forgot password link. The same error for a wrong email or password. Rate limited. |
| ✅ | Choose account type | **Built** (Individual goes to the form; Organization and Counselor open a sheet that e-mails the link; a closed kind is marked and shows the admin's words). Original spec: Individual goes straight to Create account. Organization and Counselor open a sheet "we will email you a link to sign up on a computer". The email-link endpoint is rate limited and answers the same whether the email exists or not. Respect Registration Control (a closed type is shown as closed). |
| ⚠️ | Create account | Name, email, password (4 live rules), terms, **required AI-processing consent**. The closed message is shown before the form. **(Built: the AI-processing tick, required by the form and by the server, with "What this means". Terms are not on the form: the real text is an open decision.)** |
| ✅ | Invite code | **Built as Account > Your organization (see [ORGANIZATION_MEMBERS.md](ORGANIZATION_MEMBERS.md)); not offered inside sign-up yet.** Steps 1 and 2 of 6 built (server rules: `joinViaInviteCode`, status `pending_approval`, one active organization per client; and the organization admin's Members page at `/org/members`: create and switch off codes, approve or decline people, e-mails to the person, audit). Step 3 built: `MembershipEnding` (a person leaves, or an admin removes a member from the Members page): the place is freed, later sessions with the organization's counselors are cancelled and the time given back except the next 7 days, unanswered requests are closed, the person and the affected counselors are e-mailed, and HR is e-mailed the NAME only when the person leaves by themselves. Step 4 built: the API (`/account/organization`, see API.md) and the app page Account > Your organization (enter a code, see "waiting", withdraw or leave with the consequences said first). Step 5 built: e-mail invitations (the admin invites an address on the Members page; the person, signed in with that address confirmed, accepts or declines under Account > Your organization and joins at once; no link or token). Not built yet: the Invite code step inside sign-up (where it appears in the first-time flow is open)** (see Decisions of the owner, 9): a reference code for employees who only have an ordinary e-mail address (no company domain). `MembershipService::joinViaInviteCode` is still an empty stub, `org_invite_codes` has no role and nothing can create a code yet. Skipping allowed. Automatic capitals, rate-limited code check, the same "invalid or expired" message for every reason. Joins the organization through the existing membership service. |
| ⚠️ | Sign up success | Title "One more step", the list of next steps, a button to Verify email. May be merged with Verify email if easier. (Now: sign-up goes straight to Verify email.) |
| ✅ | Verify email | Resend (30 second wait), change email, pull to check the status. **(check: built with a 60 second wait; no change-email; no pull to check)** |
| ✅ | Forgot password | The same message whether the email exists or not, resend wait. |
| ⚠️ | Reset password | Opening in the browser is accepted for now. An in-app link (deep link) is a later improvement. **Needs the owner:** a verified link needs the real web domain, the app's signing fingerprint (Android) and team id (iOS) for the hosted link files, a new package for handling links, and an API to set the new password from the app (today it is only on the web). |
| ➖ | Choose context | **Dropped (decided, see Decisions of the owner, 10):** a client can have only one active organization, so there is nothing to choose. Original spec, for the record: only with 2 or more active memberships. Choose directly, no Continue button. Sets the session context. An ordinary individual never sees it. |

## 2. Home

| | Screen | Details |
|---|---|---|
| ⚠️ | Home | Two states: a new user (a Chat card and an invitation to find a counselor) and a user with an upcoming booking (a session card; "Join session" only within 15 minutes before). A quick-help strip "Call HEAL 15555". A Feed snippet is added when Feed exists. (Now: greeting, a "Your next session" card with the soonest confirmed booking (left out while loading, on a failure, or with nothing booked; it opens the booking, where Join session lives), the way to Find, a Chat card, and a "Need help right now?" card that opens the phone lines. The Feed snippet and a Join button on the card itself are not built.) |
| ❌ | Notification panel | Postponed, and the bell hidden. No in-app notifications, email only (decision B11). |

## 3. Chatbot

The only AI component. The API key lives only in the Laravel `.env`; the app never calls the model directly. (The empty slot `ANTHROPIC_API_KEY=` exists in `.env` and `.env.example`.)

| | Screen | Details |
|---|---|---|
| ✅ | Chat | **Built, server and app** (see [CHAT.md](CHAT.md), APP_ARCHITECTURE.md "Chat"): consent gate and the Account switch, the conversation, crisis detection on the server with the amber card and an always-present "Need help now?", the model, failure handling, ending with the dominant emotion, purge. The tick at registration and the Chat card on Home are not done. Original spec: The app calls Laravel and Laravel calls the model. Text only, no personal identification sent. Blocked if the AI consent is withdrawn. Rate limit and message length limit. **Crisis detection on the server**, not depending on the model alone. Sessions and messages are stored. Ending a session stores the dominant emotion. Auto-purge after 12 months. A model failure is shown as a friendly message, not a technical error. |
| ✅ | Chat history | (Built.) Sessions grouped Today, This week, Earlier. Read only, open the whole conversation. Empty state. No delete. |
| ✅ | Counselor suggestion card | (Built.) A component inside chat. Only eligible counselors, from the same rules as search. Tap goes to the Profile. |
| ✅ | Crisis card and helplines | (Built; **numbers still to confirm**, Befrienders left out.) Amber, calm tone. Direct call buttons (HEAL 15555, Talian Kasih 15999, and Befrienders: **confirm the real numbers before use**). Buttons "Find a counselor" and "Continue chatting". It never blocks the chat and is always reachable. |

## 4. Find and book

| | Screen | Details |
|---|---|---|
| ✅ | Counselor list | Search, filters (language, focus area, mode, availability), the no-results state. **(check: built filters are session type, focus area, language, state; no availability filter)** |
| ✅ | Counselor profile | LKM verification prominent, an About tab. Availability and Reviews tabs are placeholders. **(check: built as one page, no tabs; has a "See available times" button)** |
| ✅ | Pick a slot (Mode A) | Done, per the spec and the availability API. |
| ✅ | Send a time request (Mode B) | **Built** (backend, the counselor's approve and decline on the web, and the app screen: pick a day and a start inside the counselor's weekly hours, optional note). Original spec: Date and time inside the counselor's working hours, optional note. A pending request expires automatically after 48 hours. The `booking_requests` table exists; the endpoint and the scheduler do not. The counselor needs a portal to approve, so build it together with the counselor portal. |
| ✅ | Confirm booking | Done, including idempotency and the concurrency tests (MariaDB). |
| ✅ | Booking confirmed | Summary, "View booking", "Back to home". A "request sent" variant for Mode B. "Add to calendar" is optional. (Built. The Mode B variant is not a separate screen: after asking, the app opens the request page, which says "Waiting for an answer".) |
| ✅ | Session details | (Built as a section of Booking details.) Online: the meeting link appears 15 minutes before until the end of the session, and "link not added yet" if the counselor has not pasted one (the counselor now pastes it on the web portal, Appointments page). In person: the full address and "Get directions". |

## 5. My bookings

Closes the booking loop and makes the slot-restore contract (B9) real.

| | Screen | Details |
|---|---|---|
| ✅ | Booking list | Upcoming and Past tabs. Badges: Confirmed, Pending, Starting soon. Past: Completed, Cancelled, No-show, and "Book again". Completed and No-show exist only when the counselor marks them, so Past will be limited for now. |
| ✅ | Booking details | (Built: Join session, Reschedule, Cancel.) Summary, status, a public reference number (not a sequential id). Actions by state: Join session, Reschedule, Cancel. |
| ✅ | Cancel booking | A confirmation sheet with two states (inside or outside the allowed period; the deadline comes from the server per organization). The server restores `is_available`, tells the counselor by email, and is idempotent. |
| ✅ | Reschedule | Reuses the slot picker (same counselor only). At most 2 times, the remainder shown. Claims the new slot and releases the old one in one transaction, with the same lock order as booking. **Decided (the owner said yes):** the same cancellation time limit as cancelling. The counselor is told by e-mail. Safe to repeat. |
| ✅ | Request status | **Built** (the Requests tab under Bookings, and the request page with Withdraw, View booking and Choose another time). Original spec: Pending, Approved, Declined (with an optional reason). "Withdraw request" while pending. Depends on Mode B. |

## 6. Wellness Feed

Depends on Moderate Feed in the admin part. Build them together. **Decision (owner):** everyone posts as themselves, with their name, like Twitter/Threads. No anonymous posting for now. Server and app screens done (see [FEED.md](FEED.md)); Moderate Feed (admin web) is built too.

| | Screen | Details |
|---|---|---|
| ✅ | Feed | **Built.** Open to every platform user, no AI. Reactions and comment counts. Empty state. |
| ✅ | New post | **Built** (also used to edit). Text only, 500 characters with a live count. Posted under the person's own name. (No anonymous toggle for now.) |
| ✅ | Post details and comments | **Built.**  | Full text, chronological comments, counselor replies with a badge. The comment input stays at the bottom. |
| ✅ | Post menu and report | **Built** (plus a Muted people list).  | Owner: edit and delete. Others: report (five reasons) and mute. Reports go to the Moderate Feed queue. |

## 7. Profile and settings

| | Screen | Details |
|---|---|---|
| ✅ | Profile | Done. |
| ✅ | Edit profile | Name and time zone. Changing the email needs re-confirmation. **(check: the email is changed on the website, not in the app)** |
| ⚠️ | Account settings | (The AI consent switch is built: off asks first, the server records it.) Add the AI consent toggle: turning it off disables Chat, with a confirmation dialog, and the change is recorded. (Now: change password and signed-in devices.) |
| ✅ | Data and privacy | **Built** (Account > Data and privacy; the Privacy Policy opens on the web, which is still a placeholder page; the "write to us" line shows only when the server has `SUPPORT_EMAIL` set). A static information card: chat deleted after 12 months, counselors and organizations cannot see chat content, PDPA. A link to the Privacy Policy. Data-copy requests: use the support email for now. |
| ✅ | Notification settings | **Built** (Account > Notifications: one Bookings switch; the booking e-mails obey it; the community switch comes with Feed). Email only. A bookings toggle (confirmation, reminders, changes). A community toggle only when Feed exists. **The email-sending code must read this choice.** |
| ✅ | Log out | Confirmation exists. |
| ❌ | Delete account | Do not build before the data policy is decided. Needs: what is deleted, what stays (booking records 90 days, session notes stay with the organization), complaint reporters anonymized. Type "DELETE" to confirm. |

## 8. System states

| | Screen | Details |
|---|---|---|
| ✅ | ★ Registration closed | Make sure it is also shown on Choose account type and Invite code, not only Create account. |
| ✅ | ★ Maintenance | Make sure a Platform Admin is not locked out (that is the web portal, not the app). |
| ✅ | ★ No connection and general error | Keep. |
| ❌ | ★ Help, Terms, Privacy Policy | Static pages on the web, opened in an in-app browser. Help: the support email and the list of helplines. The Terms and Privacy Policy text must be real before launch (PDPA). |

## 9. Counselor web portal (not in the original list, built because the app needs it)

On the web, for a signed-in counselor. Not part of the mobile app.

| | Page | What it does |
|---|---|---|
| ✅ | Appointments | Their own sessions: Needs marking (ended sessions: Mark completed / no-show), Upcoming (paste an `https` meeting link for an online session), Past. The client is shown by display name only. Also **Time requests**: Approve (books the session) or Decline with an optional reason. |
| ✅ | Planner | Open and remove their own bookable times (context, date, start, length, online or in person with a place of that organization), with the same buffer and overlap rules as booking. **Weekly hours**: a pattern (for example every Thursday 10:00 to 13:00) and "Open the next weeks". |
| ❌ | Dashboard numbers, Profile | Still placeholders. |
| ❌ | Personal blocks, editing a time, the counselor cancelling a booking | Not built. A cancellation by the counselor must give the time back (B9). |

## Built but not in this list

"Update required" screen, Change password, Signed-in devices, the counselor filter sheet.

## Decisions already made (do not re-ask)

B1 no fee text anywhere. B2 the no-recording acknowledgement is required and not stored. B3 the counselor sees only the client's display name. B4 booking public ids are random ULIDs. B5 the cancellation deadline comes from the server per organization. B6 a session starting inside the cancellation window shows a notice, no extra confirmation. B7 a changed slot is detected through the `expected_*` fields. B8 the upcoming-session cap counts only confirmed, not-yet-started bookings (3). B9 every path that cancels, reschedules or offboards sets `is_available` back to true. B10 no "Add to calendar" on the confirm screen. B11 email only: no push and no in-app notification. B12 no audit entry for bookings. Also: money in integer sen; complaint escalation deferred; Bahasa Melayu on hold; admin text kept out of the audit log.

## Open decisions (for the owner)

1. ~~Reschedule: same cancellation time limit?~~ Decided: yes (built).
2. Anonymous post: can an admin see the real identity? (FYP1 says yes; say so in the toggle text)
3. ~~Delete account~~ Decided (see Decisions of the owner, 3). Not built yet.
4. Crisis helpline numbers (the owner will review again when the system is complete): confirm the real numbers before use (HEAL 15555 and Talian Kasih 15999 are in the app and the server; Befrienders is left out until its number is confirmed). The crisis wording rules (English and Malay) also need a review by someone who knows how young Malaysians write.
5. The real Terms and Privacy Policy text before launch (the sign-up form has no terms tick until then). The owner agreed that a draft is written first and reviewed later, with the security review.
6. ~~Hide the tabs that are not built~~ Done: Feed built, Find left the bar (opens from Home).
7. ~~AI-processing agreement~~ Decided: **optional**. Without it a person cannot use the AI chat, but can still book counselors (see Decisions of the owner, 6).
8. Chat model and cost: no limit during testing (decided); the owner sets a per-person quota before launch. The chat model. It is `claude-opus-5-5` at low effort (about 4 seconds a reply). A cheaper or faster model can be set with `ANTHROPIC_CHAT_MODEL` in the server's `.env`; there is no spend limit except 200 messages a day per person.
9. ~~Invite code~~ Decided (see below). The original question was: **Invite code.** There is no way yet to create a code (no org-admin page), and a code has no role: which role does someone get when they join with one (student, staff, employee), and who creates the codes (an org admin on the web, with a limit and an expiry)? The app screen is simple once this is decided.
10. ~~Choose context~~ Dropped (see below). The original question was: **Choose context.** A person with two or more memberships sees counselors from all of them today. What should choosing one change in the app (only that organization's counselors? the name shown? nothing)? Until this is decided there is nothing for the screen to do.

## Decisions of the owner (31 October 2026)

1. **Invite code.** Two ways to join an organization: (a) an invitation by e-mail, active at once; (b) a **reference code** for employees who only have an ordinary e-mail address. The code tells the system "this person is an employee of this company", so they can book that organization's counselors. A person who joins with a code is **not active until the organization's HR approves them**. A code has an expiry and a use limit and is created by an organization admin on the web. A code can only make someone an employee (never a counselor or an admin). No session quota per employee.
2. **One organization per client.** A client can have only **one active organization** at a time, enforced **on the server** (not only in the app). A second join is refused with a clear message ("You are still a member of X. Leave it first"). Counselors and admins may belong to several organizations, on the web only. So there is **no Choose context screen** in the app; a client sees the public counselors and the counselors of their one organization in one list, with a label on the organization's counselors.
3. **Delete account.** Deleted: profile, AI chats, Feed posts and comments, device tokens. Kept 90 days: booking records. Kept by the organization: counselor session notes. Complaint reporters: the reporter's identity **stays stored** (decided), seen only by Platform Admins, never by the person complained about or the organization; the retention period (suggested 12 months after the case is closed, then anonymized) and the security side are discussed again when the system is complete. Confirm by typing "DELETE"; the person is signed out of every device.
4. **Leaving an organization.** Both the person (Account) and HR can end a membership. When the person leaves by themselves, the app tells them first that HR will be told, and **HR gets an e-mail** with the name only (no booking or counselor information). When HR removes someone, that person gets an e-mail too.
5. **Bookings when a membership ends.** Upcoming sessions with that organization's counselors are **cancelled**, except sessions in the **next 7 days**, which may still go ahead (a short grace so a person is not cut off the day before a session). No new booking with that organization's counselors. The person is told at once, kindly, with the helpline numbers and the public counselors; the counselor is told and any cancelled slot is given back (same as an ordinary cancellation). **HR is never told which sessions were affected.**
6. **AI consent** is optional. Terms/Privacy and Help: a draft first, reviewed by the owner (and a lawyer) when the system is complete. Helpline numbers and crisis rules: proceed as they are, the owner reviews when the system is complete.
7. **No AI quota** while testing. **Before launch:** the owner sets a quota per person.
8. **Reset-password deep link:** postponed until the owner has a domain.
9. **Support e-mail:** `ammarazizan15@gmail.com` for now (it is shown to every app user on Data and privacy, so change it to a company address before launch).
10. **Anonymous posting:** still postponed.

## Suggested order (by dependency)

1. ✅ Booking confirmed (real) → Session details → My bookings (list, details, cancel, reschedule).
2. ✅ Mode B + the counselor portal (sessions, planner, weekly hours, requests) → Request status.
3. ✅ Chat (with the AI consent, crisis detection on the server) → history → cards.
4. ✅ Feed built (identified posts). ✅ Moderate Feed built (admin web). Feed is complete.
5. ✅ Choose account type, Notification settings, Data and privacy. ✅ Onboarding. ❌ Invite code (decided, next to build), Help / Terms / Privacy (a draft first), Reset password deep link (waits for a domain). ➖ Choose context (dropped). ✅ Home's upcoming-booking card. Delete account: decided, not built yet.

Nothing built after the Pick a slot step has been viewed on a screen yet; the emulator checklist in APP_ARCHITECTURE.md is the first thing to do with eyes.
