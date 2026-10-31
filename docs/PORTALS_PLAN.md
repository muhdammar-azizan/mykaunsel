# Counselor and organization portals (web): plan and decisions

The two web portals still to be finished: **Counselor** and **Organization admin (HR)**. Platform Admin is done. This page keeps what the owner has decided and what is still open, so nothing is asked twice. Status of the pages that exist is in [APP_SCREENS.md](APP_SCREENS.md) (section "On the web, for a signed-in counselor") and [ORGANIZATION_MEMBERS.md](ORGANIZATION_MEMBERS.md).

## Decided (owner, 31 October 2026)

1. **Order:** by role. Counselor first, then the organization admin. (First counselor step proposed: the counselor cancels a booking.)
2. **Counselors join an organization** because the organization's admin assigns them: by **e-mail invitation** (first way) or by a **reference code** (second way, for a counselor whose e-mail address has no company domain). This is the same pair of ways as for employees.
3. **Client reviews:** yes. A client may review a counselor after a session (details still to settle, see below).
4. **Payments for independent counselors:** a separate, harder subject. Not decided; to be brainstormed before anything is built, so that it fits the system that already exists outside MyKaunsel.
5. **Organization reports:** detailed and many, not a single page (list proposed below; the owner will confirm).
6. **Design of the two portals:** different from the Platform Admin portal. The owner will supply a reference; nothing is redesigned before it is seen.

7. **Session notes: agreed in principle** with every safeguard listed under "Session notes" below (text only, encrypted, readable only by the counselor who wrote it, never shown to the organization admin or the Platform Admin, not shown to the client in the app, not in reports, fixed retention, named in the Privacy Policy). The organization's supervisor can not read them (decided).
8. **A counselor assigned by an organization is still verified by the Platform Admin** (KB number, practising certificate, LKM check), always.
9. **When a counselor is removed from an organization** the clients who had later sessions with that counselor are told and the sessions are cancelled (the same care as when a client leaves, but the people to tell are the clients; the counselor is told too).
10. **Organization reports:** follow the proposed list below, designed around what an HR person wants to see first (a glance page, then the detail pages, then exports).
11. **Who may be booked: two settings at the level of the organization only** (no per-counselor exception): (1) who can book our counselors' times (only members, or anyone; already built as `access_model`), and (2) may our counselors also take independent clients on MyKaunsel (no or yes; new). A counselor has one account and one KB number, so a second e-mail for freelance work is neither possible nor needed. Details in "Who may be booked" below.
12. **An organization's supervisor can NOT read session notes.**
13. **Reports:** the three levels below (a glance page, detail pages, exports), every figure with a short explanation, a minimum group size of **5** below which a figure is hidden, and a breakdown by unit or department shown only when the group is large enough.

## Open

### Session notes (counselor)
The owner wants a counselor to be able to attach a note to each session they handle. It can be done, and counselors normally keep records, but these notes are the most sensitive data in the system (health information under the PDPA). Proposed safeguards, to be decided:

- text only, no file attachments at first, with a length limit;
- stored encrypted (the note text is not readable in the database);
- read and written only by the counselor who wrote it; the organization is the custodian (it keeps them, per the earlier decision), but **no screen** of the organization admin or the Platform Admin shows the text; a formal reading (a complaint investigation) is a deliberate, audited act;
- never shown to the client in the app (they may ask for access under the PDPA);
- never used in reports;
- kept for a fixed period, then erased (the period is for the owner and a lawyer);
- named in the Privacy Policy; counts of notes written (not the text) are audited.

### Who may be booked: the organization's two settings (decided; details)
An institution such as a university has counselors for its own students and staff only; a clinic may also take the public. The system already has one switch for this (`access_model`: closed or open). The owner's point shows a second question, so two separate settings are proposed:

1. **Who can book our counselors' times:** only our members (closed, the default for a university) or anyone (open, for a clinic). Already built.
2. **May our counselors also take independent clients on MyKaunsel:** no (a university's counselor stays with the university) or yes. New. Independent practice is a membership of the platform organization, so "no" means the platform will not give an assigned counselor an independent practice.

A counselor is one person with one KB number and one account (a second account with the same KB number is refused and flagged), so a "new e-mail only for freelance work" would not work and is not needed: with setting 2 on "yes" the same account keeps its independent practice, with its own independent display name next to the organization one. With "no", the counselor can not freelance on MyKaunsel while assigned. If a counselor who already practises independently is assigned by an organization that says "no": proposed that the assignment is refused until the counselor ends the independent practice on purpose (its later sessions are cancelled, clients told).

### Counselors and organizations
- May the same counselor be assigned by several organizations? The system allows it: when opening a time in the Planner the counselor chooses which organization it is for. Proposed: yes, unless an organization's setting 2 above says otherwise.

### Reviews: decided (31 October 2026), to be built with the counselor portal
Looked after for the counselor as much as for the client. Researched only partly (Zocdoc: review only after an attended appointment, a live person moderates, verified negative reviews stay if they follow the guidelines, the provider may reply or contest but not remove).

**For the client**
- One review per completed session, 1 to 5 stars plus a few optional tags (felt listened to, comfortable, helpful, on time) and an optional comment. The questions are about the experience, not "is this counselor good" and never "did it cure me".
- A separate private box, "Tell MyKaunsel privately" (boundaries crossed, feeling unsafe): it goes straight to the Platform Admin as a possible complaint, never to the counselor, never published.
- If the session is over and the counselor has not marked it within 48 hours, the client may still review it, marked "attendance not confirmed". (decided)
- A comment with words that suggest danger shows the helpline numbers and is not published until the Platform Admin has seen it. Phone numbers, e-mail addresses and identity card numbers in a comment are removed automatically, and the form reminds the client not to write personal details.
- Reviews are asked for the day after the session, one per session, within 30 days, and can be edited for 7 days.

**For the counselor**
- New reviews arrive as a weekly summary, not one by one (kind to the counselor, and it makes it harder to guess who wrote one).
- Reviews are seen without the client's name. The counselor may flag an unfair one (abusive, false, the wrong counselor); the Platform Admin looks at both sides and removes a review only for breaking the guidelines, never because the rating is low. One public reply, and an appeal if a moderation decision seems wrong.
- Review bombing is limited by the one-per-session rule, the 30 days, and a flag to the Platform Admin for a burst of low ratings.

**For the organization** (decided)
- The organization sees the overall average and, per counselor, an average only when there are enough reviews (proposed 5), **never the comments**. Comments are read only by that counselor and the Platform Admin, so a client's stars can not be used as a performance weapon by an employer.

**For everyone**
- The public average is shown only when a counselor has enough reviews (proposed 5). No "best counselor" ranking: the list is ordered by availability and fit, not by stars, so a new counselor can still get a first client.
- Later: both sides confirming that a session took place; separating first sessions from later ones.

### Payments
To brainstorm: who pays (organization, client, or both), how an independent counselor is paid, the platform's share, refunds when a session is cancelled, invoices and tax, and how all of it matches the system that already runs outside MyKaunsel. Nothing is built before this is settled.

### Organization reports (proposed list)
Always counts without names. A figure for a group smaller than the minimum size (decided: 5) is hidden, so nobody can be recognised. Never from the chat, never from session notes. Three levels, built around what an HR person wants to see first: (1) a glance page with four cards (share of members who have used the service, sessions this month against last month, waiting for your approval, counselor capacity used), one trend graph and a "needs attention" box (approvals waiting, domain not verified, a counselor with no free time in the next 14 days, a high no-show rate); (2) detail pages; (3) exports.

- **Overview:** members (active, waiting, left), counselors, sessions this month against last month.
- **Use:** sessions by week or month; completed, no-show and cancelled; online against in person; busiest days and hours; how far ahead people book; time requests (how many, how many answered, how fast).
- **Members:** new and left by month; how they joined (e-mail domain, code, invitation); by unit or department when the group is large enough; the share of members who have booked at least once.
- **Counselors:** hours offered against hours booked (capacity), sessions per counselor, how soon the first free time is.
- **Quality:** average rating of the organization's counselors (only with enough reviews), cancellation rate.
- **Export:** CSV and PDF with the same hiding rules, for a chosen period; compare two periods.


