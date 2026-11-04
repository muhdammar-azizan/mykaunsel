# Terms and Privacy: what is missing for counselors (draft for review)

Status: **applied on 4 November 2026, as a draft, with corrections** (the pages themselves, and the corrections, are described in [LEGAL_PAGES.md](LEGAL_PAGES.md), "The counselor sections"). The text in the live pages is the source of truth; this file is kept as the reasoning and the review questions. Corrections made after cross-checking with the code: wording about LKM never claims a live connection ("the registration records we hold, together with a review by our team"); suspension and an organization ending an assignment are two separate rules written as the code behaves; the proposed "logged-out visitor can browse a public directory" sentence was dropped because no public directory exists; no "organization-assigned approval process" is described because none exists; the "delete your account" line was fixed like the "one organization" line. Original proposal follows. It is written for the owner and for a second reader (Claude chat, later a lawyer) to cross-check. Where a fact only the owner or a lawyer can supply is missing, it is marked **[TO BE CONFIRMED: ...]**, the same convention the live pages use. Nothing legal was invented to fill a gap.

Numbers in curly braces, such as {30 days}, are read from the configuration by the live pages (the value today is shown), so the text can never disagree with what the system does.

## 1. What the live pages say about counselors today

**Terms (`/terms`)** has one short section, "Counselors and sessions", written for clients: counselors are independent professionals, we check their registration details but a session is between client and counselor, cancel and move rules, sessions with an organization's counselors, come on time. It says nothing about what a counselor must do or what MyKaunsel may do to a counselor.

**Privacy (`/privacy`)** is written for clients. For counselors it only says: a counselor sees the client's name and session details; a counselor's private session note is encrypted, kept {7 years} and readable only by that counselor; a counselor can report; counselors and administrators can not delete their account in the app and must write to support.

## 2. Gaps found

### Terms

| # | Gap | Why it matters |
|---|---|---|
| T1 | **A statement that is wrong for counselors:** "You can belong to only one organization at a time." A counselor can work for several organizations and independently at once. | One text for everyone, so a counselor reads a rule that does not apply to them. |
| T2 | Nothing on **who may be a counselor**: approval by MyKaunsel, true registration details, a valid PA, what is checked and when. | The whole trust model of the product. |
| T3 | Nothing on **automatic and manual suspension**: the PA expiring suspends a profile by itself; the team may suspend after a complaint; what a counselor can still do while suspended. | A counselor can be suspended with no warning in the Terms. |
| T4 | Nothing on a counselor's **duties to clients**: honest availability, cancelling with a reason, meeting links, conduct, confidentiality of sessions. | Clients are promised "independent professionals" with no matching duty. |
| T5 | Nothing on **complaints and reviews** about counselors: who can complain, what happens, that clients can review and the counselor can reply or report a review. | A counselor is judged by a process they were never told about. |
| T6 | Nothing on the **relationship with an organization**: invited by e-mail, may leave, may be removed, what happens to sessions and times when it ends. | Real effects on sessions and clients. |
| T7 | Nothing on **third-party meeting links** (Google Meet, Zoom, Teams): the counselor chooses the tool and its own terms apply. | Sessions run on tools MyKaunsel does not control. |
| T8 | Nothing on **emergencies** from the counselor's side: MyKaunsel is not an emergency service; a counselor's own professional duty when a client may be in danger. | Safety. |
| T9 | Nothing on **ending a counselor's account**: it can not be done in the app, but the process, and what is kept, are not described. | The Privacy page says "write to support" and stops. |
| T10 | **Re-agreement:** changing the text should raise `terms_version` so that counselors (and everyone) agree to the new text. Today's version is `2026-10-31`. | We can tell who agreed to which text. |

### Privacy

| # | Gap | Why it matters |
|---|---|---|
| P1 | The list "What we keep" has **no counselor data**: KB number, PA number and PA date, the three application documents (KB certificate, PA certificate, ID or passport copy), application status and review history, LKM comparison result. | The most sensitive data in the system is not listed. |
| P2 | **Document retention is not stated:** the ID or passport copy is deleted {30 days} after the final decision; certificates of a rejected application {90 days} after; the certificates of an approved counselor stay while the account exists. | The system already does this; the page does not say so. |
| P3 | **Who sees counselor data is not stated:** clients (display name, languages, specializations, times; never photo, KB or PA number, e-mail); organization admins (name, whether the PA is expired or ending, counts of sessions, never client notes); the Platform Admin team (documents, review notes; every document opening is recorded). | PDPA transparency. |
| P4 | **Counselor activity records:** the audit log (ids and counts, never client names or note text), calendar entries and blocks, time requests. No retention period is stated for the audit log. | Transparency, and a retention rule is missing. |
| P5 | **Reviews and complaints about a counselor:** what is shown to the counselor, who reported is never shown to them, the complaint text is erased {3 years} after closing. | Fairness to the counselor. |
| P6 | **Session notes:** covered, but not what happens to them when a counselor leaves an organization or MyKaunsel. Today nothing is built for that (the docs call it an open decision). | A real gap in the system, not only in the text. |
| P7 | **Counselor photo:** an account photo can be uploaded but is never shown to clients; not said. | Expectation. |
| P8 | **Sign in with Google** was added; the Privacy page does not mention it (what is received from Google, that no password is held). Applies to every user, not only counselors. | Transparency. |
| P9 | **Counselor rights:** access, correction (names, languages, specializations are editable; KB, PA and status are changed only by MyKaunsel), and how to ask. | PDPA rights, with the one-way fields explained. |

## 3. Proposed text for the Terms

### Fix to the existing client text (T1)

Replace: "You can belong to only one organization at a time. To join another one, leave the first."
With: "As a client, you can belong to only one organization at a time. To join another one, leave the first. (Counselors can work for more than one organization: see 'If you are a counselor'.)"

### New section: "If you are a counselor"

**Who can be a counselor.** A counselor account is approved by the MyKaunsel team before it is shown to anyone. To be approved you must give true registration details (your KB number, your PA number and its validity, and the documents we ask for), and we check them against the records we can reach. You must keep them true and tell us if anything changes. Your practising certificate (PA) must be valid for as long as you are listed. [TO BE CONFIRMED: the registration body and exact professional requirements, with a lawyer.]

**You are independent.** You are an independent professional, not an employee of MyKaunsel. A session is between you and the client. You are responsible for the advice and care you give and for keeping to the rules of your profession. [TO BE CONFIRMED: the wording of what MyKaunsel is and is not responsible for.]

**Your times and your clients.** Open only times you can keep. You may cancel a session before it starts, at any hour, with a short reason that the client will see; keep it kind and do not write private details about yourself or the client. You may not move a booked session yourself: cancel it, and the client books again. The meeting link you add must be one you control; the client sees it {15 minutes} before the session starts. Online sessions run on tools such as Google Meet, Zoom or Microsoft Teams, which have their own terms and privacy rules that apply to that part of the session.

**Confidentiality.** What a client tells you in a session is confidential. Do not record a session or share what was said except as the law and your profession allow. Write session notes only for your own professional use; they stay private to you in MyKaunsel and are erased after {7 years}. [TO BE CONFIRMED: recording and confidentiality duties, and what a counselor must do if a client may be in danger, with a lawyer.]

**Emergencies.** MyKaunsel is not an emergency service. If a client may be in danger, follow your professional duty and the law, and use the numbers on the Help page.

**Organizations.** An organization can invite you by e-mail. If you accept, you work for it as well as independently, and each time you open belongs to one of them. You may leave an organization at any time, and an organization may end your assignment at any time. When that happens, your later sessions with that organization are cancelled and the clients told, requests waiting for it are closed, and the times and weekly hours you opened for it are removed. Your other work is not affected.

**Complaints and reviews.** Clients can review a session after it, and anyone can send a complaint about a counselor. The MyKaunsel team looks at complaints and may set one aside, warn you, or suspend you. You can reply to a review, and you can report a review you believe breaks the rules. The person who complained is not shown to you. [TO BE CONFIRMED: the complaint procedure and how long a counselor has to answer.]

**Suspension and ending.** Your profile is suspended automatically when your practising certificate (PA) expires, and the team may suspend it after a complaint or if these terms are broken. While you are suspended you are not listed, you cannot open new times and requests waiting for you are closed. Sessions already booked are kept unless the team decides otherwise, and you can still cancel, mark and look after them. We tell you why when we can. To lift an expiry suspension, renew your PA and contact us; the team checks the LKM record. We may remove a counselor who keeps breaking these terms.

**Ending your account.** A counselor account cannot be deleted in the app, because clients and organizations depend on its records. Write to support. [TO BE CONFIRMED: what is erased and what is kept when a counselor account ends, and for how long.]

## 4. Proposed text for the Privacy Policy

### Additions to "What we keep" (for counselors)

"If you are a counselor, we also keep: your KB number, your PA number and when it is valid; the documents you send to apply (KB certificate, PA certificate, ID or passport copy); the result of comparing your details with the LKM directory record we hold; the history and notes of the review of your application; the times you open, your weekly hours, your blocked times (and the note you may add to one), and the requests and bookings you receive; reviews and complaints about you; your photo, if you add one to your own account (it is never shown to clients); and the profile choices you make (the names clients see, languages, specializations)."

### Who can see counselor data

"Clients see the name you choose, your languages and specializations, and the times you open. They do not see your photo, your KB or PA number, your e-mail or your documents. The people who run an organization you work for can see your name, whether your practising certificate is valid or about to expire, and counts of your sessions; never what a client said and never your private notes. The MyKaunsel team that reviews applications sees your documents and your details; every time a document is opened it is recorded. Your private session notes can be read only by you."

### How long we keep counselor data

"ID or passport copy: {30 days} after the final decision on your application. Certificates of an application that was rejected: {90 days} after. Certificates of an approved counselor: while your account exists. Session notes: {7 years} after the session. The words of a complaint: {3 years} after it was closed (the record that it happened stays). Notices inside the portal: {90} days after you read them, {180} if you never do. Records of important actions (the audit log): [TO BE CONFIRMED: how long, with a lawyer; today no period is set]."

### Your rights as a counselor

"You can see and correct what you can edit yourself: the names clients see, languages, specializations and your account details. Your KB number, PA number, PA date and status come from your application and the LKM check; the MyKaunsel team changes them, so write to us if one is wrong. You can ask for a copy of your data and withdraw agreements you gave. [TO BE CONFIRMED: the contact and the time we take to answer.]"

### Sign in with Google (all users)

"If you choose to sign in with Google, we receive your name and verified e-mail address from Google. We do not receive your Google password. [TO BE CONFIRMED: whether Google sign-in is offered to counselors and organization admins as well as clients.]"

### What counselors leaving means for notes

[TO BE CONFIRMED, and a product decision first: what happens to a counselor's private session notes if the counselor leaves an organization or MyKaunsel. The earlier decision was "the organization is custodian" but nothing is built. The text can not promise anything until this is decided.]

## 5. Facts the owner must supply

1. The legal name and address of the organization that runs MyKaunsel (already marked on both pages).
2. The support e-mail address (`SUPPORT_EMAIL`).
3. What is erased and what is kept when a counselor account ends, and the retention of the audit log.
4. What happens to a counselor's private notes when they leave (a decision, not only text).
5. The complaint procedure for counselors: who decides, how long a counselor has to reply, appeal.
6. The professional duties a counselor must keep (confidentiality, recording, duty when a client may be in danger).
7. Whether Google sign-in is offered to all roles.
8. The minimum age (already marked "To be confirmed" on the Privacy page).

## 6. Questions to cross-check (for Claude chat or a lawyer)

1. Is "independent professional, session between client and counselor" enough, or should the Terms state MyKaunsel's role (a platform that introduces) more precisely?
2. PDPA 2010: is a counselor's PA/KB/ID data "sensitive personal data"? Is explicit consent needed for the documents, and for comparing them with the LKM directory?
3. Is {30 days} for the ID copy and certificates for approved counselors appropriate, or should certificates also have a limit?
4. Session notes {7 years}: is that a sensible period for counselling records in Malaysia, and who must be the custodian if the counselor leaves?
5. Is it acceptable that organization admins see whether a counselor's PA is expiring?
6. Complaint handling: what is a fair process for a counselor (notice, right to reply)?
7. Does automatic suspension on PA expiry need an advance warning stated in the Terms? (The system already reminds {30, 7 and 1 days} before.)
8. Which version number should change when this goes in (`terms_version`, `updated`), and should existing counselors be asked to agree again?

## 7. What changing the live pages would involve

- Edit `terms.blade.php` and `privacy.blade.php` (the sections above), keeping the "To be confirmed" marks and reading numbers from the configuration.
- Change `config/legal.php`: `updated` and `terms_version`. Keep `draft` on until the owner and a lawyer have reviewed the text.
- Tests: `LegalPagesTest` (the new sections appear; the numbers come from the configuration; every unconfirmed fact is marked).
- Update `docs/LEGAL_PAGES.md`.
