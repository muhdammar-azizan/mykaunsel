# Counselors (Platform Admin → Counselors)

Working notes for the Counselors page. Updated step by step as it is built (1a → 1b → 2 → 3).

## Decisions

- **"PA valid until" means valid for the whole of that day** — up to 23:59:59 in the application timezone. One helper, `App\Support\PaValidity`, is used by `CounselorProfile::isPaValid()`, `LkmVerificationService` and (from step 1b) listing and the Platform Admin pages, so nothing can disagree about the last day. Tests cover yesterday / today / tomorrow and the last second of the day.
- **Documents are never public.** KB, PA and ID/passport files live on the private disk `counselor_documents` (`config/filesystems.php`, chosen through `config/counselors.php`), under random file names, with no URL. The applicant upload (`CounselorDocumentController`) writes there and deletes the file it replaces. Existing files were moved by migration `2026_10_23_000001_move_counselor_documents_to_private_disk` (renamed, paths updated, public copy removed, unreferenced leftovers moved to `orphaned/`, the `counselor-documents` public folder deleted). The `public/storage` symlink is deliberately kept — avatars and premises photos still use it. The applicant screens never preview their own documents, so no owner-only preview route was needed.
  From step 1b, platform admins view documents only through short-lived signed routes, and every view is an audit event.
- **The PA-expiry audit key is `counselor.pa_expired_suspended`** (actor "system"). It had briefly been catalogued as `system.pa_expiry_suspension`; migration `2026_10_23_000002_rename_pa_expiry_audit_key` converted old rows and unknown legacy keys still display with a readable fallback. The scheduled PA-expiry check itself does **not** exist yet and is not part of this page.

## Shared review components (extracted from the Organizations page, no change in behaviour or look)

`status-tabs`, `review-checklist`, `review-notes`, `activity-timeline`, `reason-modal`, `confirm-modal` in `resources/views/components/platform/`. The Organizations pages now use them. The extraction was checked by rendering all 14 Organizations page states (list tabs, filtered, empty, and the detail page for pending / skipped / suspended / rejected / active organizations) before and after: the HTML is identical apart from whitespace.
