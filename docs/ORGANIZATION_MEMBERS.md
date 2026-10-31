# Organization members: reference codes, invitations, leaving

How a person comes to belong to an organization, and what happens when they leave. The owner's decisions are in [APP_SCREENS.md](APP_SCREENS.md), "Decisions of the owner". Every rule below is enforced **on the server**; the app only asks and shows.

## Rules

- **One organization per client.** A client (student, staff or employee) has only **one** organization at a time: active, waiting for approval, in a notice period or suspended all count. Alumni and offboarded do not. A second join is refused with `409 in_other_organization` and a message naming the first. Counselors and admins may belong to several (on the web only). `MembershipService::heldBy()`.
- **Three ways in:**
  1. **E-mail domain** (existing): active at once. Also follows the one-organization rule.
  2. **E-mail invitation**: the organization's admin invites an address. The person signs in to the app with that address **confirmed**, and accepts or declines under Account > Your organization. Accepting makes them an employee **at once**. No link and no token: the address is the proof. Open for 30 days, at most 100 waiting per organization. A person already waiting with a code of the same organization becomes active by accepting.
  3. **Reference code**: for employees whose address has no company domain. A code is 10 characters (no 0, O, 1, I, L), shown as `K7M29-QXR4P`, with an optional expiry and use limit, created and switched off by an organization admin (at most 20 switched on). Using it makes the person an **employee, waiting for approval** (`pending_approval`): no access to anything until the admin approves. A code can never make anyone a counselor or an admin. A wrong, expired, switched-off or used-up code all give the same answer (`422 invalid_code`). Trying codes is limited to 5 a minute.
- **Approving or declining** (organization admin, Members page): approve opens the door and e-mails the person; decline closes the request, frees the place, and e-mails the person (no reason). The person may ask again later. Two admins answering the same request: the second is told it is already dealt with.
- **Leaving** (the person, from Account, or an admin removing a member): the place is freed. Later sessions with the organization's counselors are **cancelled** and the time given back (same contract as an ordinary cancellation, B9), **except sessions starting within the next 7 days**, which still go ahead (`MembershipEnding::GRACE_DAYS`). Unanswered time requests for that organization are closed. Sessions already started or finished, and sessions with other organizations' counselors or public counselors, are untouched.
- **Who is told:** the person (what was cancelled, what still goes ahead, the helpline numbers), each counselor whose session was cancelled (date and time only), and, **only when the person leaves by themselves**, the organization's admins (the **name only**, never sessions or counselors). When an admin removes someone, the admins are not e-mailed. A person only waiting for approval who withdraws is not e-mailed to anyone.
- An admin can not "remove" someone who is only waiting (use Decline), and can not remove a counselor or an admin this way.
- No session quota per employee.

## Web (organization admin): `/org/members`

Waiting for approval (Approve / Decline), Invite by e-mail (send, list, cancel), Reference codes (create with expiry and number of people, switch off), Members (Remove). Always about the admin's current organization; an id from another organization is a 404. The page still uses the older look, like the counselor portal.

## API (all need a signed-in, verified person)

See [API.md](API.md): `GET`, `POST …/join`, `DELETE` on `/account/organization`, and `POST /account/organization/invitations/{id}/accept|decline`.

## Audit (ids only, never an address or a name)

`member.join_requested`, `member.join_approved`, `member.join_declined`, `member.code_created`, `member.code_revoked`, `member.invited`, `member.invitation_accepted`, `member.invitation_declined`, `member.invitation_revoked`, `member.left`, `member.removed`.

## E-mails

All of them go out **after** the change is saved, and a failing e-mail never undoes it: approval, decline, invitation, "your membership ended" (to the person), "booking cancelled because the client is no longer a member" (to the counselor), "a member left" (to the admins). A queue worker is not needed for these (they are sent directly), unlike the booking e-mails.

## Not built

- The Invite code step inside sign-up. The code is entered under Account > Your organization (open question: whether Home or the sign-up flow should offer it).
- Edit or suspend a member, and other member roles (student, staff) from codes: a code gives **employee** only.
- A bell notification for the admin when someone is waiting (the page lists them; there is no e-mail to the admin when someone uses a code yet).
