# Platform Admins (Platform Admin → Settings → Platform Admins)

Who can sign in to the Platform Admin area. The original spec for this page was not available, so it is kept small and uses the audit actions that were already planned (`admin.invited`, `admin.removed`).

## Decisions taken (defaults — change them in one place each)

- **An admin is a membership** with the `platform_admin` role in the Platform organization. Nothing new in the database.
- **No separate admin roles / no "role changed".** There is only one Platform Admin role in the design, so `admin.role_changed` and `admin.invite_revoked` are not used.
- **Admins are never deleted, only removed.** Removing sets the membership to `offboarded` with the date and the reason. The account and all history stay. Adding the same email again restores access.
- **A new email** gets an account (email marked verified, random password nobody knows) and an email with the normal "choose a password" link. **The link works 60 minutes** (the standard password-reset window). "Send invite again" is there until the person has signed in once.
- **An email that already has an account** (for example a counselor) keeps its own password and name and simply gets the extra role. They are emailed to sign in. *Say if you want this blocked.*
- **Two safety rules:** you cannot remove yourself, and the last active admin cannot be removed. The admin rows are locked while the count is checked, so two admins removing each other at the same moment cannot empty the list.
- **Removal needs a written reason** (10–1000 characters), kept in the audit log. A removed admin is blocked on their next click (the existing membership check). The person is **not** emailed about being removed.
- **Not built:** changing an admin's own email/name from here (they do it in Account), seeing what an admin did from this page (use the "See the history" link, which opens the Audit Log filtered to Platform admins).

- **The first admin of a real server** is made in the terminal, not here: `php artisan platform:create-admin` (hidden password prompt, never an argument; the Account page's password rules; e-mail counts as verified). It refuses when an active admin already exists unless `--force`, and refuses an e-mail that already has an account (it never changes an existing account). It is audited as a system action, `admin.created_from_console`, with no e-mail and no password. The demo `PlatformSeeder` (well-known password) **only runs in `local` and `testing`** and stops with an error anywhere else. See `docs/SETUP.md`.

## What is there

- **List** with two tabs: Active (name, email, last sign-in or "Not signed in yet", two-factor on/off, date added) and Removed (when and why).
- **Add admin** modal: name + email, with a warning that admins can read complaints and counselor documents.
- **Send invite again** (only before the first sign-in), **Remove** (reason modal; hidden on your own row).
- Everything goes through `PlatformAdminService`: each change and its audit entry share one transaction, emails go out after the commit and a failed email never undoes the change. No password is ever emailed or logged.
- A removal is logged **before** the status changes, so the audit entry keeps the person's name (non-staff are stored by id only).
