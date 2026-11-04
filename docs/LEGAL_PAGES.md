# Privacy Policy, Terms of Use and Help

Three public pages on the website, linked from the app (Account > Help, Terms of Use, Privacy Policy) and from each other: `/privacy`, `/terms`, `/help`. One text for the app and the web. They are **drafts** written from what the system really does; the owner (and a lawyer) review them when the system is complete (owner's decision, see [APP_SCREENS.md](APP_SCREENS.md)).

## How they stay honest

- **A draft says so.** While `config/legal.php` `draft` is on (`LEGAL_DRAFT`, default true) every page starts with a yellow note: "This is a draft that is still being reviewed." Turn it off only after the review.
- **Facts the owner has to supply are marked** with a yellow "To be confirmed:" (a `<mark>`), so they can be found by searching for it. Nothing was invented to fill them.
- **Numbers come from the configuration**, not from the text: the Privacy Policy reads the chat retention (`chat.retention_months`), notice retention (`notifications.retention.*`), complaint text retention (`complaints.retention.closed_years`) and the days a deleted account's bookings are kept (`account.deleted_booking_days`). The helplines on Help and Terms come from `chat.helplines`; the support address from `SUPPORT_EMAIL`. If a number changes in the system, the page changes with it (`LegalPagesTest` checks this).
- **They stay readable during maintenance**, because the phone numbers must always be there (`platform-settings.maintenance_allowed_routes`).
- Change `config/legal.php` `updated` whenever the text changes, and `terms_version` when it changes in a way people must agree to again (a new account stores it in `users.terms_version` with `terms_accepted_at`).

## To be confirmed (the owner supplies these)

1. The legal name and address of the organization that runs MyKaunsel (Privacy Policy, Terms of Use).
2. The support e-mail address (`SUPPORT_EMAIL`; for now the owner's own address, to be replaced by a company address before launch).
3. Where the AI provider keeps the words of a chat and for how long, once the contract is final (Privacy Policy). The page already says the words leave Malaysia to be answered by Anthropic.
4. The minimum age, and what happens for people under it (Privacy Policy).
5. The limits of MyKaunsel's responsibility, and the law that applies (Terms of Use): **to be written with a lawyer**.
6. **Added 4 November 2026 with the counselor sections** (each is a yellow marker on the page; none was filled in by the developer):
   - Terms: the registration body and the exact professional requirements for a counselor, with a lawyer.
   - Terms: the wording of what MyKaunsel is and is not responsible for (the existing marker, now also in "You are independent").
   - Terms: the recording and confidentiality duties, and what a counselor must do if a client may be in danger, with a lawyer.
   - Terms: the complaint procedure and how long a counselor has to answer.
   - Terms: what is erased and what is kept when a counselor account ends, and for how long.
   - Privacy: how long records of important actions (the audit log) are kept, with a lawyer. **No period is set in the system today.**
   - Privacy: the contact a counselor writes to, and how long we take to answer.
   - Privacy: what happens to a counselor's private session notes when the counselor leaves an organization or MyKaunsel. **A product decision first: nothing is built for it** (see SESSION_NOTES.md).
   - Privacy: whether Google sign-in stays available to counselors and administrators (today it signs in any existing account whose e-mail Google has verified, and never creates one; two-factor still applies).
   - Privacy: the exact process and timeline for telling people and the authorities after a data breach, with a lawyer.

## Statements to check against the system before launch

These are written as the intended behaviour. Some depend on parts that are decided but not all verified end to end, so check them in the security review:

- "Counselors and organizations can not read your chat", and what an organization's administrators can see about members (only name, e-mail, join and leave).
- That a person who reports a counselor is never shown to the counselor or the organization.
- That the website uses no cookies except sign-in and form protection (it loads its font from Google Fonts, and the page says so).
- That the Platform Admins' important actions are all in the audit log.

## What the pages say (summary)

- **Privacy Policy:** what is kept, the chat (optional, what is sent, the provider abroad, the danger-word rules), who sees what, how long things are kept, deleting the account (erased / ended / kept), the person's rights under the PDPA 2010 and the Commissioner, cookies, children, the sign-up reminder list, changes, helplines.
- **Terms of Use:** what MyKaunsel is and is not (not an emergency service), the account (one organization at a time, for clients), counselors and sessions, **a section for counselors** (cancel and move until the deadline, leaving an organization), the Feed rules and moderation, the chat, what is not allowed, limits, changes.
- **Help:** the helplines, the support address, and short answers (book, cancel or move, a reference code, the chat, a forgotten password, deleting the account).

## The counselor sections (draft, 4 November 2026)

Source and reasoning: [LEGAL_COUNSELOR_DRAFT.md](LEGAL_COUNSELOR_DRAFT.md) (the proposal that was cross-checked before it went in). `config/legal.php` **`draft` stays on**: nothing here is final until the owner and a lawyer have reviewed it. `terms_version` is `2026-11-04` and `updated` is `4 November 2026`.

- **Terms, "If you are a counselor":** who can be a counselor (approved by the MyKaunsel team, apply through the website, document and registration checks plus human review; an organization can only invite a counselor MyKaunsel has already approved and never verifies credentials), being independent, times and clients (cancel until it starts, no moving a booked session, the meeting link shown {booking.meeting_link_visible_minutes} minutes ahead, third-party meeting tools), confidentiality and notes ({session-notes.retention_years} years), emergencies, organizations, complaints and reviews, **two separate rules** (suspension; an organization ending the assignment) and ending the account.
- **Suspension and an organization ending an assignment are different, and the text says so.** Suspension covers the whole counselor (every place at once): not listed, no new times, **all** waiting requests closed, open times left alone; booked sessions are kept when the suspension is automatic (PA expired), and the team may choose to cancel upcoming sessions when it suspends for another reason. An organization ending the assignment (or the counselor leaving it) affects only that organization: later sessions cancelled, its waiting requests closed, its unbooked times and weekly hours removed, nothing else touched. This is what the code does (`CounselorStatusService`, `OrgCounselors::endAssignment`); **the owner should confirm it is the intended product behaviour.**
- **Two sentences that were only true for clients were fixed:** "only one organization at a time" now says "As a client" and points to the counselor section, and "delete your account in the app" says "(counselors and administrators write to support)".
- **Privacy, "If you are a counselor":** what is kept about counselors, who can see it, how long (ID copy `counselors.retention.ic_days`, rejected certificates `counselors.retention.rejected_certificates_days`, notes, complaint words, portal notices: all read from the configuration), rights, notes when a counselor leaves (marked). Two more short sections for everyone: **Signing in with Google** and **If something goes wrong with your data**.
- **No claim of a live connection to LKM.** The pages say the checks use "the registration records we hold" (a copy, not a live connection) together with a review by the team, in both Terms and Privacy.
- **No public directory is claimed.** To find a counselor you must be signed in with a confirmed e-mail address (`PUBLIC_COUNSELOR_DATA.md`), and a counselor of a closed organization is shown only to that organization's members. The pages say exactly that.
- **No "organization-assigned" approval route is described, because none exists.** An organization only invites a counselor MyKaunsel has already approved. The `org_assigned` counselor type exists in the code only in demo seed data; no live flow creates it (some code comments still speak of counselors "an organization assigned"; they describe that demo data).
- **Re-agreement is not built.** `terms_version` is stored only when an account is created; raising it records who agreed to which text but **does not ask anyone who already has an account to agree again.** Whether to build a "please agree to the new terms" step is a future product decision to scope separately.
- **Kept as a question for the lawyer, not as a marker on the page:** whether organization admins may see that a counselor's PA is expired or about to expire (they do today: the Counselors page of the organization shows it).
- Tests: `LegalPagesTest` (the sections are there, every number follows the configuration, every marker is still present, no live-LKM or public-directory claim, the two rules, the version).

## Not built

- Asking people who signed up before the tick existed (they have no `terms_version`) to agree, and asking everyone again when `terms_version` changes.
- Malay versions of the three pages.
- Telling people by e-mail or in the app when the text changes in a way that matters (the pages promise it).
