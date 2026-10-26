# Counselors (Platform Admin → Counselors)

Working notes for the Counselors page. Updated step by step as it is built (1a → 1b → 2 → 3).

## Decisions

- **"PA valid until" means valid for the whole of that day** — up to 23:59:59 in the application timezone. One helper, `App\Support\PaValidity`, is used by `CounselorProfile::isPaValid()`, `LkmVerificationService` and (from step 1b) listing and the Platform Admin pages, so nothing can disagree about the last day. Tests cover yesterday / today / tomorrow and the last second of the day.
- **Documents are never public.** KB, PA and ID/passport files live on the private disk `counselor_documents` (`config/filesystems.php`, chosen through `config/counselors.php`), under random file names, with no URL. The applicant upload (`CounselorDocumentController`) writes there and deletes the file it replaces. Existing files were moved by migration `2026_10_23_000001_move_counselor_documents_to_private_disk` (renamed, paths updated, public copy removed, unreferenced leftovers moved to `orphaned/`, the `counselor-documents` public folder deleted). The `public/storage` symlink is deliberately kept — avatars and premises photos still use it. The applicant screens never preview their own documents, so no owner-only preview route was needed.
  From step 1b, platform admins view documents only through short-lived signed routes, and every view is an audit event.
- **The PA-expiry audit key is `counselor.pa_expired_suspended`** (actor "system"). It had briefly been catalogued as `system.pa_expiry_suspension`; migration `2026_10_23_000002_rename_pa_expiry_audit_key` converted old rows and unknown legacy keys still display with a readable fallback. The scheduled PA-expiry check is described in "Daily PA check" below.

## Shared review components (extracted from the Organizations page, no change in behaviour or look)

`status-tabs`, `review-checklist`, `review-notes`, `activity-timeline`, `reason-modal`, `confirm-modal` in `resources/views/components/platform/`. The Organizations pages now use them. The extraction was checked by rendering all 14 Organizations page states (list tabs, filtered, empty, and the detail page for pending / skipped / suspended / rejected / active organizations) before and after: the HTML is identical apart from whitespace.

## Step 1b — list, read-only detail, secure document viewing

**Pages.** `/platform/counselors` (tabs Pending / Active / Suspended / Rejected with live counts; search by name, KB number or email; filters for type (independent, any organization, or one organization), PA status (valid / expiring within 30 days / expired) and "flagged"). `/platform/counselors/{id}` is read-only: independent applicants get the LKM card (submitted vs LKM record, checked live), documents, duplicate-KB attempts, applicant details and activity; approved / suspended counselors also get profile, credentials, a PA banner, and counts-only statistics. Organization counselors only ever appear under Active / Suspended and never get the application cards. Nothing on these pages shows a client name or what happened in a session.

**Data.** `counselor_profiles` gained `submitted_at`, `submission_count`, `reviewed_by/at`, `suspension_reason/at/by/source` (platform | complaint | system) and `review_checklist`. Documents moved into `counselor_documents` (one row per KB / PA / ID, with status unreviewed | ok | needs_replacement, note, upload time, `purged_at`); the three old path columns were copied over and dropped. New tables: `counselor_review_notes`, `counselor_kb_attempts`.

**Who can be found and booked** — one rule, `CounselorProfile::scopeListable()` / `isListable()` (the mobile search must use the scope): approved, PA still valid (whole last day), and at least one active counselor membership in an *active* organization. Independents work through the Platform organization (always active), so the same rule covers them. A suspended profile or an expired PA blocks every context at once.

**Documents.** Never public. The page asks `GET /platform/counselors/{id}/documents/{doc}/link` for a signed URL (2 minutes, tied to that admin's id, platform admins only); `GET /platform/counselor-documents/{doc}` verifies the signature, checks it is the same admin, serves PDF / JPG / PNG inline with `nosniff` and `no-store`, and writes `counselor.document_viewed`. Asking for a link is not a view; a PDF viewer's follow-up Range requests are not counted as new views. Purged files answer "removed under the retention rules".

**Duplicate KB.** When someone signs up with a KB number that already has an account, the sign-up form shows its usual message (nothing about the owner) and `CounselorKbAttemptRecorder` stores KB, typed name, IP and time on the *original* account — at most one record per KB + IP per hour — plus `counselor.kb_duplicate_flagged` (reference id only; the typed name is not in the audit log). Retention of these attempt rows is not decided yet; they are kept while the account exists.

**Dashboard (not wired yet).** `App\Support\CounselorAttention` gives the pending count, "LKM could not be verified" and duplicate-KB queries. `CounselorProfile::scopeLkmUnverified()` is a live check: missing record, not active, PA expired, or name / PA number differ.

**Samples.** `CounselorReviewSeeder` (safe to run twice): pending with a full match; pending with a flagged document and a duplicate attempt; pending resubmitted after a rejection; pending whose LKM record changed after applying; active with the PA expiring in 12 days; suspended by the system (PA expired, with its audit entry) and after a complaint; a rejected application; and an account others tried to re-register (with sessions and complaints for the statistics). Organization counselors come from `CounselorSeeder`. The documents are tiny made-up files on the private disk.

**Not built yet (Steps 2 and 3):** approve / reject, document marks, notes, checklist, "re-run LKM check", emails, suspend / reactivate, session handling, renewal reminder, the retention command, and the applicant-side resubmission (`counselor.resubmitted`, `submission_count`).

## Step 2 — approve, reject, document marks, checklist, notes, re-run LKM, emails

All of it is for **independent** counselors only; the routes answer 404 for organization counselors (no approval flow). Platform admins only (403 for everyone else).

- **Approve** (`CounselorReviewService::approve`): in one transaction, with the row locked and its status re-checked. The LKM check is re-run at confirmation (not the page the admin looked at earlier). Blocked when the LKM record is missing, not Active, or the PA has expired (the last day still counts), or when any of the three documents is missing / purged / marked "Needs replacement". A **name or PA-number mismatch** is only a warning: the admin must tick "I have reviewed this discrepancy", and the discrepancies are written into the audit entry (`acknowledged_discrepancies`). Not every document needs an explicit OK, and the review checklist never blocks.
- **PA date refresh:** the profile column is the source of truth. At approval and on "Re-run LKM check" its `pa_valid_until` is refreshed from the LKM record **only if the record exists, has this KB number and is Active**; old → new is audited. Otherwise nothing is overwritten. Only the date is refreshed — never the PA number or the name — so a mismatch there keeps showing as a warning.
- **Reject:** reason (10–1000 characters), quick templates (Documents unclear / LKM record mismatch / PA expired / Other), and a selector for the documents to upload again. The chosen documents are marked "Needs replacement" (each logged as `counselor.document_flagged`); the email lists the reason and exactly those documents.
- **Two admins:** one wins. The loser sees "already approved/rejected (by …)" and nothing changes — no audit entry, no email.
- **All or nothing:** the status change, document marks, PA refresh and audit entry roll back together if any of them fails. Emails are sent after the commit; a mail failure never undoes a decision.
- **Document marks:** OK / Needs replacement with a short note (optional, 255 characters), pending applications with a file only; audited. These are what the applicant's resubmit screen (not built here) will read. An applicant uploading a document again resets it to un-reviewed.
- **Checklist** (name matches LKM, documents legible, no duplicate KB, PA not expired) and **internal notes** reuse the shared components; saved at once, shared between admins, reference only, never shown to the applicant. The note text is not copied into the audit log (only its id).
- **Re-run LKM check:** a button on the LKM card; every run is audited (`counselor.lkm_rechecked`, outcome, date change). A missing / inactive record gives "The LKM number could not be verified." plus the reasons.
- **Emails:** applicant on approve and reject (`CounselorApproved`, `CounselorRejected`); platform admins when a new application arrives (`NewCounselorApplication`, honouring each admin's "New counselor application" setting). Suspend / reactivate emails come in Step 3. With `MAIL_MAILER=log` they only go to the log; a queue worker is not needed for these.
- **Applicant side (storage only, no screens):** `CounselorDocumentController` now sends admins **one** email when the third document completes the set, and one more when a rejected applicant sends documents again. A resubmission goes through the locked `transitionTo()` path: `submission_count` + 1, `submitted_at` reset, `rejection_reason` cleared (the reason stays in the audit log / Activity), and `counselor.resubmitted` is audited with the attempt number.

**Still to come (Step 3):** suspend / reactivate, session handling, PA banner reminder email, the retention command for ID copies, the dev-only LKM-date tool, and the Dashboard wiring (the count/query methods are ready in `CounselorAttention`).

## Step 3 — suspend, reactivate, sessions, PA reminder, document retention

For **approved** counselors, independent or organization. Platform admins only.

**Suspend** (`CounselorStatusService::suspend`): reason required (10–1000). In one transaction (row locked, status re-checked, audit entry written together; all or nothing): status → suspended with reason, time, who, source `platform`. They drop out of search at once (`listable()` needs "approved") and cannot open new slots.
- **Sessions already booked** — radio: *Keep* (default, nothing touched) or *Cancel upcoming sessions and notify clients*. Cancelling only changes sessions that are still **confirmed** and on/after today, so it is safe to repeat; the audit entry holds counts and booking ids only (never a client's name).
- **Pending session requests** are always closed (status `expired`, audited) and the requesters emailed, in both cases.
- **Calendar slots are not touched.** They stay as they were; the counselor checks their planner after being reactivated.
- **Emails** (after the commit): the counselor (with the reason), the active admins of their organization(s) if they work in a non-platform organization (without the platform's reason), and each affected client. The client email contains only the counselor's name and the time, is queued (`ClientSessionNotice implements ShouldQueue`, after commit) — **a queue worker must be running** (`php artisan queue:work`) or those emails wait in the `jobs` table.
- Complaint-originated suspensions (source `complaint`) will come from the Complaints page; this page records `platform`.

**Reactivate** (`reactivate`): only from suspended. First the PA date is refreshed from the LKM record (only if it exists, has this KB number and is Active). It is **blocked** — nothing changes, the date refresh is rolled back, the admin is told why — if the record is missing or not active, or the PA is still expired (the last day still counts). Clears the suspension fields (the old reason stays in the audit log), emails the counselor.

**LKM re-check requests:** when a counselor asks for their LKM record to be checked again (after renewing the PA), the request waits under LKM Directory → Review Requests (see LKM_DIRECTORY.md). The counselor's page shows a notice with a link while one is waiting, and the activity list shows the request and the decision. Approving updates the LKM record and refreshes the profile's PA date but never reactivates a suspended counselor: that stays the decision described under Reactivate.

**Dev-only LKM date tool:** a shortcut for local testing only. On a counselor's page a dashed "Dev only" box sets the PA valid-until date on their LKM snapshot record. The route is registered **only** in the `local` and `testing` environments (and the action also refuses elsewhere); it does not exist in production.

**Renewal reminder:** a button when the PA is expired or runs out within 30 days (approved or suspended counselors). Emails the counselor; at most one per 24 hours (`config/counselors.php`). The audit entry `counselor.renewal_reminder_sent` is written only after the email really went.

**Retention** — `php artisan counselors:purge-documents [--dry-run]`, scheduled daily at 02:30 (needs the scheduler / cron). **First retention job in the app; the pattern for later ones.**
- ID / passport copy: deleted **30 days after the final decision** (approved — including later suspended — or rejected and not resubmitted).
- KB and PA certificates: kept for approved counselors as proof of verification; for an application that **stays rejected**, deleted after **90 days**.
- Pending applications are never touched; a resubmitted application is pending again, so nothing of it is purged.
- Both periods are in `config/counselors.php` (`COUNSELOR_RETENTION_IC_DAYS`, `COUNSELOR_RETENTION_REJECTED_DAYS`). **A conscious decision to review before launch.**
- Per document, in chunks: the row is updated (path, original name and size removed, `purged_at` set) **together with** a system audit entry (`counselor.document_purged`, no personal data), and the file is deleted only **after** that commits. The row stays as the record that it existed. If a file cannot be deleted it is reported and the command exits with an error; the row already says "purged", so nothing in the app can reach it — fix the disk and run again. If the audit entry cannot be written, nothing is removed.
- An applicant can upload a removed document again; that restores the row.

**Scheduler-produced data:** the daily PA check (below) produces the `system` suspension with source "System (PA expired)", the red PA badge and the activity entry "Suspended automatically: PA expired" (sample `KB13090` shows how it looks).

**Dashboard:** still not rewired; `CounselorAttention` has the counts and queries ready.

## Daily PA check (`php artisan counselors:check-pa-expiry [--dry-run]`)

Scheduled daily at **00:10 in the application timezone** (needs the scheduler / cron). That is right after the last valid second of the "valid until" day (see `PaValidity`), so a PA valid until 22 Oct is suspended on the run of 23 Oct. Everything is in `CounselorPaExpiryService`. Safe to run twice or late; one counselor failing never stops the others (the command exits with an error and tries them again next run).

1. **Expired PA → suspended by "system"** (approved counselors only; pending, rejected and already-suspended are never touched). First the LKM directory is checked: if LKM already shows a newer date for that KB number (record exists and is Aktif) the profile is simply updated (audit `counselor.lkm_rechecked`, actor System) and **not** suspended. Otherwise: status → suspended, source `system`, `suspended_by` empty, audit `counselor.pa_expired_suspended` (actor System), same rules as a manual suspension — they leave search, **pending requests are closed and the clients told**, the counselor and the admins of their organization are emailed.
2. **Booked sessions are kept** (not cancelled automatically). *Open question: say if expired-PA suspensions should cancel upcoming sessions.*
3. **Reactivating stays manual** (Counselors page): it is blocked until LKM shows a valid PA, and refreshes the date from LKM.
4. **Automatic reminders** at 30, 7 and 1 days before the PA runs out (`config/counselors.php` → `pa_reminder_days`), to approved counselors, **once per step per PA date**: the step is remembered on the profile (`pa_reminder_for`, `pa_reminder_days`). If the check runs late only the current step is sent; a renewed PA date starts again. The email is sent first; the profile and the audit entry (`counselor.renewal_reminder_sent`, actor System) are written only if it really went (a failure is retried next day).
5. `--dry-run` lists who would be suspended / updated from LKM / reminded and changes nothing.

**Timezone note:** `config/app.php` has `timezone => UTC`, and `PaValidity` uses it, so "valid until 22 Oct" ends at 23 Oct 00:00 UTC = 08:00 in Malaysia. Set the application timezone to `Asia/Kuala_Lumpur` if the PA should end at Malaysian midnight (the schedule time follows it automatically).

