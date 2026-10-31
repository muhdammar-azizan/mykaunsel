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

## Statements to check against the system before launch

These are written as the intended behaviour. Some depend on parts that are decided but not all verified end to end, so check them in the security review:

- "Counselors and organizations can not read your chat", and what an organization's administrators can see about members (only name, e-mail, join and leave).
- That a person who reports a counselor is never shown to the counselor or the organization.
- That the website uses no cookies except sign-in and form protection (it loads its font from Google Fonts, and the page says so).
- That the Platform Admins' important actions are all in the audit log.

## What the pages say (summary)

- **Privacy Policy:** what is kept, the chat (optional, what is sent, the provider abroad, the danger-word rules), who sees what, how long things are kept, deleting the account (erased / ended / kept), the person's rights under the PDPA 2010 and the Commissioner, cookies, children, the sign-up reminder list, changes, helplines.
- **Terms of Use:** what MyKaunsel is and is not (not an emergency service), the account (one organization at a time), counselors and sessions (cancel and move until the deadline, leaving an organization), the Feed rules and moderation, the chat, what is not allowed, limits, changes.
- **Help:** the helplines, the support address, and short answers (book, cancel or move, a reference code, the chat, a forgotten password, deleting the account).

## Not built

- Asking people who signed up before the tick existed (they have no `terms_version`) to agree, and asking everyone again when `terms_version` changes.
- Malay versions of the three pages.
- Telling people by e-mail or in the app when the text changes in a way that matters (the pages promise it).
