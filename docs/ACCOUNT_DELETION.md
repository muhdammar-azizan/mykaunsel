# Deleting an account

A client deletes their own account (the app, Account > Delete account, and the web profile page). The owner's decisions are in [APP_SCREENS.md](APP_SCREENS.md), "Decisions of the owner", 3. This page says what the server does; the app screen (Account > Delete account) shows the same three lists before it asks for the password and the word DELETE.

## What the server does (`AccountDeletion`)

The account is **not** removed from the database: bookings, session notes and complaints point at it. What identifies the person is erased and the row stays as an **anonymous stub** (`users.deleted_at` set, the name "Deleted user", an e-mail address nobody can use, a password nobody knows). Everything below happens in one transaction; the e-mails go out after it and a failing e-mail never undoes anything.

| | |
|---|---|
| **Erased** | name, e-mail address, profile photo (the file too), two-factor secrets, last sign-in details, every sign-in (phones and web), AI chat conversations (messages and sessions), Feed posts and comments (the words are blanked and the rows marked deleted, because other people's replies and reports point at them), likes, muted people, in-app notices, booking request keys, the claim on an allowed-member row |
| **Ended** | every **later** confirmed booking, of any organization or a public counselor, with **no 7 day grace** (the person is gone): the status changes and the time is given back in the same transaction (B9), and each counselor is e-mailed the date and time (the client is called "A client"). Time requests nobody answered are withdrawn. The membership of an organization is ended, and its admins are e-mailed the **name only**, as when a member leaves (a person who only waited for approval leaves quietly) |
| **Kept** | booking records (see the 90 day rule below), what a counselor or organization wrote about a session, the person's reports of a counselor (the reporter stays on record: owner decision; retention to be discussed again), reports of Feed writing, the history of AI consent |
| **Audit** | `account.deleted`: ids of cancelled bookings and organizations only, never a name or an address |
| **E-mail to the person** | sent to the old address (which is then gone from our records): what went and what stays, and "write to support if this was not you" |

**Refused:** counselors and administrators (anyone with a counselor profile or a counselor, organization-admin or platform-admin membership): other people depend on their records. API: `403 staff_account`. A second delete: `404 already_deleted`.

## 90 days (`accounts:purge-deleted`, daily 03:40)

Ninety days (`config/account.php` `deleted_booking_days`) after the account was deleted, its bookings and time requests are deleted. A booking is **kept**, still without a name, when a session note or a complaint points at it (those belong to the organization and to the complaint). `--dry-run` counts only. One system audit entry (`account.records_purged`) with counts.

## API

`DELETE /api/v1/account` with `current_password` and `confirm` = `DELETE` (capitals). Needs the signed-in person; limited like other password checks (5 a minute). Answers `{deleted: true, cancelled: n}`. A wrong password or word: `422` with the field named. On success every sign-in of the person is gone, the one making the request included, so the app signs out locally.

The web profile page (Breeze "Delete account") uses the same service.

## Not built

- Terms and Privacy Policy text that says all this (a draft comes first).
- A grace period to change one's mind (the owner has not asked for one).
- Deleting a counselor's or an administrator's account.
