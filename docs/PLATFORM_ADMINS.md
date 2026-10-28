# Platform Admins (Platform Admin → Settings → Platform Admins)

Who can sign in to the Platform Admin area: how admins are invited, how they accept, and how they are removed.

## Decisions taken

### Who an admin is

- **An admin is a membership** with the `platform_admin` role in the Platform organization. There is **one tier only**: no "role changed", no deactivate/reactivate, no Super Admin. This is a conscious decision (`admin.role_changed` is in the audit catalogue but never written). **Any admin can invite and remove any other admin.** Review this if the number of admins grows.
- **Admins are never deleted, only removed.** Removing sets the membership to `offboarded` with the date and the reason. The account and all history stay.
- **Safety rules (always on):** you cannot remove yourself; the last active admin cannot be removed; the admin rows are locked while the count is checked, so two admins removing each other (or the same person) at the same moment cannot empty the list or leave a half-done state.

### The first admin of a real server

Made in the terminal, not on this page: `php artisan platform:create-admin` (hidden password prompt, never an argument; the Account page's password rules; the e-mail counts as verified). It refuses when an active admin already exists unless `--force`, and refuses an e-mail that already has an account (it never changes an existing account). It is audited as a system action, `admin.created_from_console`, with no e-mail and no password. The demo `PlatformSeeder` (well-known password) **only runs in `local` and `testing`** and stops with an error anywhere else. See `docs/SETUP.md`.

### Invitations (`admin_invitations`)

- **Inviting changes nothing for the person.** No account, no role, until they accept. An invitation is a row with: name, email (lower case), a hash of the token, the sender, the time limit, how many times it was sent, and its state.
- **The token** is 256 random bits. Only its **SHA-256 hash** is stored; the token itself exists only in the emailed link (and in the queued email job until it is sent). The link is also a **signed link** with the same time limit, and it is tied to the invited email (the email is read from the invitation, never from the request).
- **Time limit: 48 hours** (`config/platform-admins.php`, `PLATFORM_ADMIN_INVITATION_HOURS`). **Single use.**
- **One live invitation per email**, also when two admins invite the same address at the same moment (unique index on `active_email`, which is filled only while an invitation is pending). A pending invitation past its time makes way for a new one (it is marked expired).
- **Send again** gives the SAME invitation a new token and a new time limit: the old link stops working at once. Once a minute, at most 5 sends in all for one invitation. **Revoke** cancels it at once. Both are on the **Invitations** tab.
- **Limits:** 10 requests a minute per route, and one admin can send 20 new invitations an hour (`config/platform-admins.php`).
- **States on the Invitations tab:** Pending, Accepted, Expired (pending and past its time) and Revoked. The tab shows who sent it, when, and how many times.
- **The email** (`PlatformAdminInvitation`) is queued and sent after the commit (a queue worker must be running). It does **not** say whether the address already has an account.
- **A link only works while the admin who sent it still has access.** When an admin is removed, the waiting invitations they sent are revoked in the same transaction (`admin.invite_revoked`, cause `sender_removed`); the check is also made when the link is opened. "Send again" by another admin makes that admin the sender.
- **Personal data (retention):** an invitation holds the name and email it was sent to only while it is needed. `php artisan admin-invitations:purge` (daily 03:45, `--dry-run` to count) **empties the name and email 30 days after** an invitation was **accepted** (counted from `accepted_at`), **revoked** (`revoked_at`) or **expired** (the day it ran out, `expires_at`, or the day a newer invitation replaced it, `superseded_at`). The row stays as an empty record: id, status, dates, who invited and who accepted (as user ids) and the token hash (not personal), plus `personal_data_erased_at`. The list then shows "Details erased under the retention rule" with no actions, and such an invitation can no longer be sent again (invite the person again). A **pending** invitation still within its time is never touched. One system audit entry `admin.invitations_purged` with the count only (never a name or email). **30 days is a conscious choice, not a legal rule:** change `platform-admins.invitation.retention_days` (env `PLATFORM_ADMIN_INVITATION_RETENTION_DAYS`) and document why.

### Accepting (the page behind the link)

The same "no longer valid" page (HTTP 410) answers for a wrong signature, unknown token, used, replaced, revoked, expired or sender-removed link: the page does not say which. Pages are not cached, not indexed and send no referrer.

| The email… | What the person does | What happens |
|---|---|---|
| has **no account** | chooses a password (the Account page's rules) | the account is created (email verified through the link), the role is given |
| has an account with a **verified** email | types the account's own password. If the account has **two-factor**, they must be signed in first (so the second factor was asked) | the role is given; password, name and sessions are untouched. 5 wrong passwords lock that invitation for 15 minutes |
| has an account whose email was **never verified** | must choose a **new** password | the old password stops working, **every session, remember-me cookie, API token and pending password-reset link of the account is ended**, the email counts as verified through the link, then the role is given |

A removed admin who is invited again is restored the same way. Nobody is signed in automatically after accepting: they are sent to the sign-in page.

### Removing

- **Removal needs a written reason** (10–1000 characters), kept in the audit log (`admin.removed`, with the person's name and `signed_out: true`). Removing **ends every way back in, in the same transaction**: their sessions in the database (when `SESSION_DRIVER=database`), their remember-me token (replaced by a new random one, so old cookies stop working) and their API tokens. If any of that fails, the removal and its audit entry are undone. This signs the person out of **everything** (also any other role they hold, such as counselor), because one account has one set of sessions; they can sign in again for the other roles.
- **Second line of defence:** `CheckMembershipStatus` runs on every request of the signed-in area. If the Platform Admin membership of the session is no longer active (removed or suspended), the person is **signed out on the spot** and sent to the sign-in page with a message. This ends a session that could not be deleted (sessions kept in files, cache or Redis) and covers choosing the Platform Admin role again. Other roles keep the existing "Akses Disekat" page.
- **The person is emailed** (`PlatformAdminRemoved`, queued, sent after the commit): their Platform Admin access has ended and they are signed out on all devices. **It does not include the reason or who removed them.**
- **Older audit entries they wrote are never changed**: the name and role copied at the time and `actor_user_id` stay exactly as they were.

## Audit entries

| Action | Target | Metadata (never an email, name, token or password) |
|---|---|---|
| `admin.invited` | the invitation ("Admin invitation #12") | summary, `expires_hours` |
| `admin.invite_resent` | the invitation | summary, `send_number` |
| `admin.invite_revoked` | the invitation | summary (and `cause` when the sender was removed) |
| `admin.invite_accepted` | the new admin (by name, like other staff) | `invitation_id`, `how` (`new_account`, `existing_account`, `unverified_account_new_password`), summary |
| `admin.removed` | the removed admin | `reason`, `signed_out` |
| `admin.created_from_console` | the first admin | summary, `forced` |

Every entry is written in the same transaction as the change it records.

## What is there

- **List** with three tabs: **Active** (name, email, last sign-in or "Not signed in yet", two-factor on/off, date added), **Invitations** (see above) and **Removed** (when and why).
- **Invite admin** modal: name + email, with a warning that admins can read complaints and counselor documents.
- **Remove** (reason modal; hidden on your own row).
- Everything goes through `AdminInvitationService` and `PlatformAdminService`: each change and its audit entry share one transaction, emails go out after the commit and a failed email never undoes the change. No password is ever emailed or logged.

## Not built

- Changing an admin's own email/name from here (they do it in Account).
- Two tiers / Super Admin, role change, deactivate and reactivate.
- Deleting old invitation ROWS: only the name and email are erased (see "Personal data"); the empty record stays.
- The invitation link is in the queued email job until it is sent; anyone who can read the jobs table in that moment can read it. It is removed when the job finishes.
