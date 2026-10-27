# Complaints (Platform Admin → Complaints)

Complaints clients made about counselors. **Step 1: list, detail, internal notes, activity. Step 2: status changes, warning, suspending from a complaint.**

## Decisions taken (defaults — the original spec for this page was not available; change them in one place each)

- **Only Platform Admins read a complaint's text.** Not org admins, not the counselor it is about (403). The list never shows the text and does not even load the column.
- **No new status.** The existing four: Open, Under review, Resolved, Dismissed. ("Escalated" was not introduced.)
- **Only the Platform Admin side is built.** The complaint form inside the app (client side) is not part of this web repo.
- **Only the counselor is emailed, and only for a warning or a suspension.** The reporter is never emailed, and nobody is emailed on a status change.
- **Reporter:** their name is shown (a complaint needs a person to follow up with), never their email. **Session:** organization, date, mode and booking status only — no session notes (there is no content to show anyway).
- Viewing a complaint is not audited (unlike counselor documents). Say if you want that.

## What is there

- **List** (`/platform/complaints`): tabs Open / Under review / Resolved / Dismissed with live counts; search by counselor name, KB number or reporter name (the text is deliberately **not** searched); filters for organization (through the session's booking; independent practice = the Platform organization) and counselor. The last two are what the "View complaints" links on the Counselors and Organizations pages open. Open and under-review complaints are oldest first, with an age chip (amber from 5 days, red from 8, like applications); closed ones most recently touched first. Pagination and distinct empty states.
- **Detail:** what was reported (escaped plain text, line breaks kept, marked confidential), counselor (KB, status, type, a count of their other complaints with a link), reporter, session, internal notes, activity.
- **Internal notes** (shared component): admins only; saved with `complaint.note_added` in one transaction; the note text is not copied into the audit log, only its id.
- **Activity:** filed + whatever the audit log holds for the complaint (`complaint.*`). The audit log only ever holds the complaint's id and "Complaint #N" as its name — never the text.
- The Dashboard's "Active complaints" is open + under review, the same as the first two tabs.

## Handling a complaint (Step 2)

- **Start review** (open → under review): no reason needed.
- **Resolve / Dismiss**: a written outcome is required (10–1000 characters). Closing is **final — no reopening**. The outcome is shown on the closed complaint and kept on it (the audit entry `complaint.status_changed` holds only old → new; see "Where the words are" below).
- **Send warning**: the admin's message is emailed to the counselor (never says who complained or what was written). Does not change the complaint's status or the counselor's account. Kept in `complaint_warnings`; audit: `complaint.warning_issued` (the warning's id only). Not possible once the complaint is closed.
- **Suspend counselor**: same modal and rules as on the counselor's page (reason, keep/cancel upcoming sessions, counselor emailed), with source `complaint`. Written together with `complaint.counselor_suspended` in one transaction (the reason is on the counselor's profile). Only while the complaint is open/under review and the counselor is approved. It does **not** close the complaint by itself.
- Two admins at once: the status row is locked and re-checked; the form carries the status the admin saw, so a stale page is refused with "already … (by …)".
- Emails go out only after the database commit; a failed email never undoes the action.

## Sample data

`ComplaintReviewSeeder` (safe to run twice; every text starts with "[Sample]"): every status; 9, 6, 3 and 1-day-old open ones; an organization counselor, a clinic counselor, an independent counselor; one without a session; a very long text; one with HTML characters (shown as plain text); one with a note and history.

---

# Update: audit log, retention, history, list (after the Complaints audit)

## Who sees what

| | Complaint text, closing reason, warning messages | Reporter's name | Internal notes | Reporter's email |
|---|---|---|---|---|
| Platform Admin | yes (until erased) | yes (or "Deleted user") | yes | never |
| Organization Admin | **no** (no page, 403 on every complaint route) | no | no | no |
| The counselor it is about | no, only the warning/suspension message the admin chose to send, which never says who or what | no | no | no |
| The reporter | not emailed, no page here | – | no | – |

Checked at the query level too: the list does not load the text column, search does not read it, the counselors and organizations pages show counts only, and the notifications other people receive hold only what the admin wrote to them.

## The sanction ladder

1. **Start review** (no reason). 2. **Warn** (an email with the admin's message; no change to the account; one warning per complaint per 24 hours). 3. **Suspend** (the same logic and the same modal as the Counselors page, source "complaint"; the counselor's organization admins are told the counselor name only; clients of cancelled sessions are told the name and time only). 4. **Resolve or Dismiss** with a written outcome: final, never reopened, and "No action" is a dismissal with that outcome. A suspension does not close a complaint by itself, and a complaint does not need a sanction to be closed.

Suspending checks everything inside one transaction with the complaint row locked first and then the counselor's profile row (always in that order): a complaint another admin has just closed, or a counselor who has just been suspended, changes nothing and sends nothing.

## Where the words are (audit log = no words)

The audit log holds only the kind of action, the old and new status and ids: `complaint.status_changed` (old → new), `complaint.warning_issued` (`warning_id`), `complaint.counselor_suspended` (`counselor_profile_id`, `sessions`), `complaint.note_added` (`note_id`), `complaint.text_purged` (counts). What the admin writes is kept with the complaint instead:

- the **closing reason** on the complaint (`closing_reason`, with `closed_at`),
- each **warning's message** in `complaint_warnings` (admin, time, message),
- the **suspension reason** on the counselor's profile (`suspension_reason`, as before).

The form still says "do not paste the complaint text". **Suspension from a complaint:** the reason is kept ONLY on the counselor's profile (`suspension_reason`, linked to the complaint by `suspension_complaint_id`) and the counselor's own audit entry `counselor.suspended` no longer repeats it: it holds the source (`complaint`), the complaint id, a `has_reason` flag, a short line "Suspended after complaint #N (the reason is kept with the complaint)" and the session options. The Counselors page shows the reason from the profile with a link "See complaint #N", and its activity shows that short line; once the reason has been erased it says so. **An ordinary suspension from the Counselors page is unchanged** (its entry still holds its reason). The email the counselor receives still holds the reason the admin wrote (it is the message to them). Reactivating clears the reason and the link. Audit entries written before this change are not rewritten (development data can be reset with the seeder).

## Retention (a decision, taken)

- A **closed** (resolved or dismissed) complaint keeps its text for **3 years from the day it was closed** (`config/complaints.php` → `retention.closed_years`). Then `php artisan complaints:purge-text` (daily 03:15, `--dry-run` to look) erases **everything written about it**: the complaint text, the closing reason, the messages of the warnings sent, the **internal notes** (the note rows stay, empty: who wrote each and when), and the **reason of a suspension that came from it** (while the counselor is still suspended, the profile just stops showing the reason). The record stays: reference, status, dates, who it was about, who made it (or "Deleted user"), and how it ended. The page then says the text was erased under the retention rule. The same rule covers every word an admin writes on a complaint: closing reason, warning message, suspension reason and notes.
- **Open and under-review complaints are never touched**, however old.
- One system audit entry (`complaint.text_purged`) with counts only (complaints, warning messages, notes): no words, no names.

## Deleted accounts

- **A reporter** who deletes their account does not block anything: the link to them becomes empty ("Deleted user"), the complaint stays in full.
- **A counselor** keeps the restriction: the database refuses to delete a user who has a complaint about them, open or closed (also after the text is erased, since the record is still about them). The account-deletion feature, when it is built, must enforce in the app that a counselor with an **open or under-review** complaint cannot delete their account, and must settle what happens after closing (anonymising the counselor on the record is the likely rule). This is recorded here so it is not forgotten; it is not built yet.

## List and history

- Tabs: **All**, Open, Under review, Resolved, Dismissed (Open is shown first by default), with counts.
- Search: counselor name, KB number, reporter name, or the **reference**: `#12` and `CMP-12` look at the reference only; a plain `12` looks at the reference and at names/KB numbers. The text is never searched.
- Filters: counselor, organization, and **filed from / to** (the admin's own days, both ends included). Order: **Most urgent** (what still needs a decision first, the longest waiting at the top, then the closed ones, most recently touched first), **Newest**, **Oldest**. The URL keeps all of it.
- **Counselor history** on a complaint: "3 complaints, 2 reporters, 1 dismissed" (all complaints about this counselor, how many different people, how each ended) and the other complaints with a link, date and status, never text. A **pattern notice** appears when there are **3 or more complaints in the last 90 days** (`complaints.pattern`). It is information for the admin only; nothing happens automatically.

## Not built yet (waiting for the Organization Admin portal and the app)

- **Escalation by an organization admin is not built**, and no escalation column, source filter or source badge exists. When the Org Admin portal is built, the planned fields on `complaints` are: `source` (direct / escalated), `escalated_by` (user), `escalated_at`, `escalation_reason`, with the organization's own notes shown to Platform Admins as context; escalated and unreviewed complaints will then come first under "Most urgent", and the audit key `complaint.escalated_received` will be added.
- The notification preference **`escalated_complaint`** on the Account page exists but **does not send anything yet** (there is nothing to escalate). It is left in place.
- Complaints cannot be created from anywhere yet (no app form, no Org Admin portal): they exist only as sample data (`ComplaintReviewSeeder`), so the escalation flow and the app's filing flow cannot be tested end to end.
- **The reporter is never emailed** when a complaint is closed. The reason: the reporter should not learn what sanction a counselor received. Revisit when the user app is built: possibly a general "your report was reviewed" status, with no details.
