# Portal pages checklist (counselor and organization admin)

Tick a page when it is built. Layout, design and decisions: see PORTALS_PLAN.md. Colours follow the MyKaunsel theme; structure follows the owner's two reference screenshots (dark fixed header, left sidebar, cards, fixed footer). Platform Admin keeps its own design.

## Shared (build first)
- [x] Layout: fixed dark header (menu, logo, search, notifications bell, help, avatar), left sidebar, content area, fixed footer (`x-portal-layout`, `config/portal-nav.php`; the 8 existing counselor and organization pages already use it)
- [x] Notifications bell and list (`/portal/notifications`)
- [ ] Account menu (profile, security, sign out) and role switch: menu, sign out and role switch are done; the Account page it links to is still the old basic profile page (security and notification settings for portal users are not built)

## Counselor portal
- [ ] Dashboard: today's sessions, requests waiting for an answer, next free time, short notices
- [ ] Bookings: list (upcoming, past, cancelled), one booking page, counselor cancels with a reason
- [ ] Time requests: members asking for a time, accept or decline
- [ ] Schedule: weekly slots, blocked times (leave, holidays)
- [ ] Session notes: private notes per session, list and edit
- [ ] Reviews: reviews received, reply, per the agreed review rules
- [ ] Profile: photo, bio, specialties, languages, session types, verification status

## Organization admin portal
- [ ] Dashboard: members, counselors, sessions this month, items waiting for approval
- [ ] Members: list, waiting for approval, invitations, invite code, remove or end membership
- [ ] Counselors: the organization's counselors, add or remove, who may be booked (the two settings)
- [ ] Reviews: reviews of the organization's counselors
- [ ] Reports: overview, use, members, counselors, quality; CSV and PDF export; compare two periods
- [ ] Subscription: current plan and end date, pay by the online page, invoices, receipts, request renewal
- [ ] Organization settings: name, details, domains, access model
