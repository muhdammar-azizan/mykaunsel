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
- **Resolve / Dismiss**: a written outcome is required (10–1000 characters). Closing is **final — no reopening**. The outcome is shown on the closed complaint and kept in the audit log (`complaint.status_changed`, old → new + reason).
- **Send warning**: the admin's message is emailed to the counselor (never says who complained or what was written). Does not change the complaint's status or the counselor's account. Audit: `complaint.warning_issued`. Not possible once the complaint is closed.
- **Suspend counselor**: same modal and rules as on the counselor's page (reason, keep/cancel upcoming sessions, counselor emailed), with source `complaint`. Written together with `complaint.counselor_suspended` in one transaction. Only while the complaint is open/under review and the counselor is approved. It does **not** close the complaint by itself.
- Two admins at once: the status row is locked and re-checked; the form carries the status the admin saw, so a stale page is refused with "already … (by …)".
- Emails go out only after the database commit; a failed email never undoes the action.

## Sample data

`ComplaintReviewSeeder` (safe to run twice; every text starts with "[Sample]"): every status; 9, 6, 3 and 1-day-old open ones; an organization counselor, a clinic counselor, an independent counselor; one without a session; a very long text; one with HTML characters (shown as plain text); one with a note and history.
