# Waitlist for closed sign-ups (Platform Admin → Registration Control → Waitlist)

When a sign-up is closed (see `REGISTRATION_CONTROL.md`), people can leave their email address to hear when it reopens. The promise to them is small and the whole design keeps it: **one email, only when that sign-up reopens, with a way out**.

## Scope

- **Organization and Counselor sign-up on the web only.** Individuals sign up in the mobile app: no waitlist, no tab, no count anywhere (not on the cards, not in the counts the Dashboard banner will use), nothing in the seeder, and the routes for it answer 404.
- The form is shown on the "sign-up closed" page of that kind and on the account-type page, **only while that kind is closed, maintenance is off and the secret key is set**. It is never cached (the pages are sent `no-store`, so the form's CSRF token is never an old one). If the sign-up is already open when the form arrives, nothing is stored and the person gets a short note with a link to the real sign-up form.

## What is stored

`waitlist_entries`: the address (trimmed, lower-case), the kind (`organization` / `counselor`), the status (`waiting` / `notified`), when they signed up and when they were notified, and a keyed hash of the address (to find the row from an opt-out link). Nothing else: no name, no IP. **Unique on (kind, address) in the database**; two requests at the same instant make one row (the loser is ignored, and gets the same answer).

`waitlist_suppressions`: for people who **opted out**: only a keyed hash (kind + hash), never the address. "Opted out" is therefore not a row status; the page shows how many opted out, never who.

## The public form

- **Consent line** on the form: "We will email you once, only when {organization|counselor} sign-up reopens. We do not share your email, and you can opt out in that email." plus a link to the Privacy Policy (`/privacy`, a placeholder page for now; change it with `PRIVACY_POLICY_URL`).
- **No double opt-in** (a decision): at most **one** notification email per address per kind. After they are notified they get no more, unless that address signs up again (then it goes back to waiting).
- **Always the same answer** — same status code, same redirect, same message — whether the address is new, already listed, notified before, opted out before, or the form was filled by a bot (honeypot field): "Thank you. If this address can be added, we will email you once when … sign-up reopens." The same few database queries run on every path (tested), so it does not take noticeably longer for one case.
- **Limits**, counted the same way whatever the address, so they cannot be used to find out who is listed: 5 attempts an hour per IP, 3 a day per address, and 100 an hour per kind of sign-up (`waitlist.limits`, for spread-out attacks). Over a limit: "Too many attempts. Please try again later." (shown the same way for everybody).
- A page left open until its session expired comes back with a friendly line instead of the 419 error page.

## Opting out

Every email has an opt-out link (and the `List-Unsubscribe` + `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers, so mail programs show an "Unsubscribe" button).

- The link carries only a **keyed hash of the address and a signature** (no address in the URL), has **no expiry**, and works during maintenance. It is made with a **dedicated secret** (`WAITLIST_HMAC_KEY`, *not* `APP_KEY`, so rotating `APP_KEY` breaks nothing).
- Opening the link shows "Stop these emails?" with a button (so mail scanners that open links cannot opt anybody out). The button — or the mail program's one-click POST, which is exempt from CSRF because the link itself is signed — does the opt-out: the address is **deleted** and only its hash is kept. It is idempotent, and it shows "You are opted out" **even if the address is already gone** (for example removed by the retention rule), and the choice is still remembered.
- Signing up again with an address that opted out is not stored; the person is told the same thing as everyone.
- Opt-out is **per kind** (opting out of the organization list says nothing about the counselor list).
- **Keep `WAITLIST_HMAC_KEY` stable.** Changing it makes old opt-out links stop working and old opt-outs stop matching. Make one with `php artisan waitlist:key`. Without a key (or one shorter than 32 characters) the waitlist is **off**: no form, the public route is 404, and the admin section says why.

## Notify all (Platform Admin)

- One button per kind, in the Waitlist section. It is **off, with the reason written next to it**, while that sign-up is still closed, while maintenance is on, when nobody is waiting, or when the key is missing. The server checks the same, so a crafted request cannot get around the button. The confirmation window shows how many people will be emailed.
- Reopening a sign-up **never sends anything by itself**.
- **Each waiting address is claimed atomically** (`waiting -> notified` with a condition) before its email is queued, so running it twice, or two admins at once, can never give an address a second email. Emails are queued **after the commit** and spread out at 60 a minute (`waitlist.send_per_minute`); a queue worker must be running.
- When an email is about to go out the job checks again: if the person opted out or was removed meanwhile, nothing is sent; if the sign-up was closed again, the address goes back to **waiting** (an email saying "open" never leaves while it is closed); the address is also marked "emailed" by a conditional update first, so the same job run twice still sends one. If sending fails for good, the address goes back to **waiting** and can be notified again.
- The email is in English, says the sign-up is open, links to **the normal sign-up page** of that kind (nobody is registered automatically), says it is the only email, and has the opt-out link.

## Admin page

Registration Control → **Waitlist** (also linked from "N waiting on the waitlist" on the Organizations and Counselors rows): a tab per kind, the numbers (waiting, already emailed, opted out — a count only), search by address, 25 a page, an empty state, times in the admin's own time zone, **Remove** (with confirmation, on request of that person; it is not an opt-out), **Export CSV** and **Notify all**. Platform Admin only (403 for others).

**Export:** the active tab, `Waiting` and `Notified` only (opted-out people are not in it), formula-looking cells (`=`, `+`, `-`, `@`) made safe, limited to 10 requests a minute and 10,000 rows.

## Retention (`php artisan waitlist:purge`, daily 03:00, `--dry-run` to look)

- An address is deleted **30 days after it was notified**, and an address **never notified 365 days after it signed up** (`waitlist.retention`, or `WAITLIST_RETENTION_NOTIFIED_DAYS` / `WAITLIST_RETENTION_WAITING_DAYS`).
- **The opt-out hashes are kept on purpose** — a conscious decision: they are the only way to honour "do not email me" after the address itself is gone. They are keyed hashes, not addresses.

## Audit log (category "Waitlist")

`waitlist.notified` (kind, `sent` = claimed and queued, `failed` = could not be claimed because it changed meanwhile), `waitlist.exported` (kind, count), `waitlist.entry_removed` (record id and kind only), `waitlist.purged` (system, counts). **Never an email address in any entry** (tested). Sign-ups and opt-outs are **not** audited. Each entry is written in the same transaction as the change it records.

## Setup

- `php artisan migrate` (two tables).
- `php artisan waitlist:key`, then put the `WAITLIST_HMAC_KEY=…` line in `.env` (the local `.env` and the tests already have one).
- The server's cron must run `php artisan schedule:run` every minute (retention), and a queue worker must run for the emails.
- `php artisan db:seed --class=WaitlistSeeder` for sample addresses (organization and counselor only, a few already notified, a few opt-outs as hashes).

## Decisions and where this differs from the first checklist

- Individuals have no waitlist (they use the app).
- "Opted out" is a count, not a row status, because opting out deletes the address and keeps only a hash.
- Opt-out is per kind, and its link first shows a button instead of acting on a plain page view.
- The audit events are named `waitlist.*` (their own category), not `settings.*`.
- Not built: double opt-in (decided against), emails in Bahasa Melayu (English only for now), and a waitlist API for the mobile app.
