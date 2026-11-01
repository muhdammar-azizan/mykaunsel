# Portal pages checklist (counselor and organization admin)

Tick a page when it is built. Layout, design and decisions: see PORTALS_PLAN.md. Colours follow the MyKaunsel theme; structure follows the owner's two reference screenshots (dark fixed header, left sidebar, cards, fixed footer). Platform Admin keeps its own design.

## Shared (build first)
- [x] Layout: fixed dark header (menu, logo, search, notifications bell, help, avatar), left sidebar, content area, fixed footer (`x-portal-layout`, `config/portal-nav.php`; the 8 existing counselor and organization pages already use it)
- [x] Notifications bell and list (`/portal/notifications`)
- [ ] Account menu (profile, security, sign out) and role switch: menu, sign out and role switch are done; the Account page it links to is still the old basic profile page (security and notification settings for portal users are not built)

## Counselor portal
- [x] Dashboard: today's sessions, requests waiting for an answer, next free time, short notices (`CounselorDashboard` service; shows the next session when nothing is today, sessions waiting to be marked, profile and PA notices)
- [x] Bookings: list (needs marking, upcoming, past with cancelled), one booking page (`/counselor/appointments/{id}`), counselor cancels with a reason (required, 3 to 300 characters; no deadline; the time opens again; the client gets an e-mail with the reason and an app notification `booking.cancelled`)
- [x] Time requests: members asking for a time, accept or decline (own page `/counselor/requests` and a sidebar item; the Bookings page keeps a pointer when some are waiting; the reason on a decline is optional and the client sees it)
- [x] Schedule: weekly slots, blocked times (leave, holidays) (`/counselor/planner`; blocks are whole days or part of a day, one day or up to 60 days; refused over a booked session; free times inside a block can not be booked until it is removed; `CounselorBlocks`)
- [x] Session notes: private notes per session, list and edit (`/counselor/notes`; see SESSION_NOTES.md: encrypted, only the writer reads, erased after 7 years (placeholder))
- [x] Reviews: reviews received, reply, flag (`/counselor/reviews`; the counselor's page and the server rules are built; the client's screen, the Platform Admin moderation pages and the weekly summary are NOT: see REVIEWS.md)
- [x] Profile: verification status (read only), names clients see, languages, specializations, meeting app, gap between sessions, accept time requests (`/counselor/profile`; picked from lists in `config/counselors.php`). Photo and a written bio are NOT built: they need an approval step first (PUBLIC_COUNSELOR_DATA.md)

## Organization admin portal
- [ ] Dashboard: members, counselors, sessions this month, items waiting for approval
- [ ] Members: list, waiting for approval, invitations, invite code, remove or end membership
- [ ] Counselors: the organization's counselors, add or remove, who may be booked (the two settings)
- [ ] Reviews: reviews of the organization's counselors
- [ ] Reports: overview, use, members, counselors, quality; CSV and PDF export; compare two periods
- [ ] Subscription: current plan and end date, pay by the online page, invoices, receipts, request renewal
- [ ] Organization settings: name, details, domains, access model
