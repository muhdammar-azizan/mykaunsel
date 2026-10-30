# What clients may see about counselors (mobile app search)

This is a **privacy rule set**, not just an API description. Change it only on purpose, and change the tests with it
(`tests/Feature/Api/CounselorDirectoryTest.php` fails if a field is added or a forbidden one appears).

Code: `CounselorDirectory` (who and when) · `CounselorPublicPresenter` (which fields, written out one by one) ·
`CounselorDirectoryController` · routes in `routes/api.php`.

## Who can ask

- Signed in **with a confirmed email** (no anonymous access, so the list is hard to scrape).
- 20 counselors a page, 40 requests a minute and 600 an hour per person (`api-directory`).
- Answers are `Cache-Control: private, no-store`.

## Who is listed

A counselor is listed only when they are **listable** (approved, practising certificate valid through the whole last day,
working for at least one active organization) **and** at least one of their organizations is visible to the asker:

| Organization | Visible to |
|---|---|
| Platform (independent counselors) | everyone |
| Open organization (`access_model = open`), active | everyone |
| Closed organization (`access_model = closed`), active | only its **active members** |
| Suspended / pending / rejected organization | nobody |

A counselor who works in a closed organization **and** independently is shown to a stranger as an independent only:
the closed organization's name, display name and slots never reach them.

## What is shown

| | Card (search results) | Profile |
|---|---|---|
| id (random 26-character public id, never the row number) | ✅ | ✅ |
| Name: the name the counselor chose for that way of meeting them (`display_name_independent` / `display_name_org`), else their registered name | ✅ | ✅ |
| "Verified" (always true: only verified counselors are listed) | ✅ | ✅ |
| Where they work: "independent" and/or organization names (only those the asker may see) | ✅ | ✅ |
| Specializations | first 3 + the count | all |
| Languages | ✅ | ✅ |
| Session types on offer (online / physical), from their bookable slots | ✅ | ✅ |
| City and state of in-person slots | ✅ | ✅ |
| Next available slot (time with +08:00, length, type, city/state, which way) | ✅ | ✅ |
| Whether they take requests | – | ✅ |
| Upcoming slots (up to 10) | – | ✅ |

**A slot is shown only if:** it is marked available, is inside the **booking window** (starts at least 2 hours from now and is no later than 30 days from today, Malaysian time: `config/booking.php`), is not booked
(a cancelled booking frees it), does not clash with another entry on the counselor's calendar (a personal block or a booked session, with the buffer: [AVAILABILITY.md](AVAILABILITY.md)), belongs to an organization the asker may see, and the counselor still works there. It is the same rule the booking screen uses, so a time shown here can always be booked there. (It used to be 60 days and any time in the future.)

## What is NEVER shown (and why)

| Never | Why |
|---|---|
| Street address, postcode, coordinates, the place's name, location photos | Some counselors practise from home: an exact address is a safety risk. Shown only after a confirmed booking (booking phase). |
| KB number, PA number, PA date | Identification data nobody needs to choose a counselor. "Verified" carries the trust. |
| Email, phone, documents | Personal data. |
| Review notes, complaints, suspension, rejection, checklist | Internal. |
| Working hours, buffer, meeting provider and links | Internal / only for the booking itself. |
| Photos | No approval step exists yet; an unreviewed photo is a risk. The app shows initials. |
| The row id | Sequential ids reveal how many counselors there are and allow walking through profiles. |

## What is not recorded

What someone searches for ("trauma", "depression") is sensitive. The server does **not** store, audit, log or cache search
text or filters, and there is no search analytics. (Web-server access logs contain the address of each request: keep their
retention short and do not ship query strings to third-party log tools.)

## Hidden looks like missing

A counselor who exists but may not be seen answers exactly like one that does not exist (`404`, same message). A made-up id
and a number are `404` too.

## Known limits (deliberate, for now)

- Sorting happens in PHP over the asker's candidates (fine for hundreds of counselors; when there are thousands,
  precompute "next available" on the profile).
- 20 per page and the slot time zone are in `config/counselors.php` → `directory`. The lead time and the horizon are in `config/booking.php`.
- The session-type and state filters ask "has a bookable time" before clashes with the counselor's other entries are taken off, so in a rare case a counselor can match a filter while their card shows no time.
- Slots with no organization (`context_org_id` empty) are never shown (their visibility cannot be decided).
- Specializations and languages are free text. The filter lists are built from real data, case-insensitively, so
  "Grief" and "grief " are one choice; a controlled list can be added later.
