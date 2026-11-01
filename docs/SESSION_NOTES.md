# Counselor session notes

Built 2 November 2026 (counselor portal, `/counselor/notes`). The most sensitive text in the system (health information under the PDPA), so every safeguard agreed on 31 October 2026 is in place. Rules: `App\Services\SessionNotes`. Tests: `CounselorSessionNotesTest`.

## What it does
- One private note per session, written by the counselor of that session. Text only, up to 5,000 characters (`config/session-notes.php`).
- A note can be written once the session has **started** and was **not cancelled** (confirmed, completed or no-show). Open it from the Session notes page or from the session's own page.
- Saving again replaces the note. **Delete note** erases it for good.

## Safeguards
- **Encrypted at rest:** the text is stored with Laravel's `encrypted` cast (the app key). The database never holds readable text. Keep `APP_KEY` safe and backed up: a note can not be read without it.
- **Only the counselor who wrote it** can read, change or delete it. Another counselor, and an unknown note, are the same 404.
- **No other screen shows it:** not the organization admin, not the Platform Admin, not complaints, not reports, not the client's app. The list page only knows *whether* a note exists and never loads the text. The model hides `body` from arrays and JSON.
- **Pages are never cached** by the browser (`Cache-Control: no-store, private`).
- **Audit:** only that a note was saved or deleted, with the booking id. Never the text, never its length (`session_note.saved`, `session_note.deleted`, `session_note.purged`).
- **Retention:** erased for good **7 years after the session** by `php artisan session-notes:purge` (daily 03:10). 7 is a placeholder: the owner and a lawyer decide (`retention_years`).
- **Named in the Privacy Policy** (what we keep, who can see it, how long).

## Still to decide
- The retention period (owner and a lawyer).
- Access for the client under the PDPA: the Privacy Policy says they may ask; there is no screen for it, and none is planned (a formal reading is a deliberate, audited act by the owner).
- What happens to a counselor's notes if their account is deleted or they leave an organization (the organization is the custodian per the earlier decision; nothing is built for this yet).
