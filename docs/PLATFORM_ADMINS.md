# Platform Admins (Platform Admin → Settings → Platform Admins)

Who can sign in to the Platform Admin area. The original spec for this page was not available, so it is kept small and uses the audit actions that were already planned (`admin.invited`, `admin.removed`).

## Decisions taken (defaults — change them in one place each)

- **An admin is a membership** with the `platform_admin` role in the Platform organization. Nothing new in the database.
- **No separate admin roles / no "role changed".** There is only one Platform Admin role in the design, so `admin.role_changed` and `admin.invite_revoked` are not used.
- **Admins are never deleted, only removed.** Removing sets the membership to `offboarded` with the date and the reason. The account and all history stay. Adding the same email again restores access.
- **A new email** gets an account (email marked verified, random password nobody knows) and an email with the normal "choose a password" link. **The link works 60 minutes** (the standard password-reset window). "Send invite again" is there until the person has signed in once.
- **An email that already has an account** (for example a counselor) keeps its own password and name and simply gets the extra role. They are emailed to sign in. *Say if you want this blocked.*
- **Two safety rules:** you cannot remove yourself, and the last active admin cannot be removed. The admin rows are locked while the count is checked, so two admins removing each other at the same moment cannot empty the list.
- **Removal needs a written reason** (10–1000 characters), kept in the audit log (`admin.removed`, with the person's name and `signed_out: true`). Removing **ends every way back in, in the same transaction**: their sessions in the database (when `SESSION_DRIVER=database`), their remember-me token (replaced by a new random one, so old cookies stop working) and their API tokens. If any of that fails, the removal and its audit entry are undone. This signs the person out of **everything** (also any other role they hold, such as counselor), because one account has one set of sessions; they can sign in again for the other roles.
- **Second line of defence:** `CheckMembershipStatus` runs on every request of the signed-in area. If the Platform Admin membership of the session is no longer active (removed or suspended), the person is **signed out on the spot** and sent to the sign-in page with a message. This ends a session that could not be deleted (sessions kept in files, cache or Redis) and covers choosing the Platform Admin role again. Other roles keep the existing "Akses Disekat" page.
- **The person is emailed** (`PlatformAdminRemoved`, queued, sent after the commit; a queue worker must be running): your Platform Admin access has ended and you are signed out on all devices. **It does not include the reason or who removed them.**
- **Older audit entries they wrote are never changed**: their name and role copied at the time and `actor_user_id` stay exactly as they were.
- **Any admin can invite and remove any other admin** (there is one tier). This is a deliberate decision: review it if the number of admins grows.
- **Not yet:** pending invitations sent by a removed admin are not cancelled, because invitations are still the password-reset link (they have no record). This comes with the invitation table.
- **Not built:** changing an admin's own email/name from here (they do it in Account), seeing what an admin did from this page (use the "See the history" link, which opens the Audit Log filtered to Platform admins).

- **The first admin of a real server** is made in the terminal, not here: `php artisan platform:create-admin` (hidden password prompt, never an argument; the Account page's password rules; e-mail counts as verified). It refuses when an active admin already exists unless `--force`, and refuses an e-mail that already has an account (it never changes an existing account). It is audited as a system action, `admin.created_from_console`, with no e-mail and no password. The demo `PlatformSeeder` (well-known password) **only runs in `local` and `testing`** and stops with an error anywhere else. See `docs/SETUP.md`.

## What is there

- **List** with two tabs: Active (name, email, last sign-in or "Not signed in yet", two-factor on/off, date added) and Removed (when and why).
- **Add admin** modal: name + email, with a warning that admins can read complaints and counselor documents.
- **Send invite again** (only before the first sign-in), **Remove** (reason modal; hidden on your own row).
- Everything goes through `PlatformAdminService`: each change and its audit entry share one transaction, emails go out after the commit and a failed email never undoes the change. No password is ever emailed or logged.
- A removal is logged **before** the status changes, so the audit entry keeps the person's name (non-staff are stored by id only).
