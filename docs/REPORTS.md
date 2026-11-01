# Organization reports

Design agreed 31 October 2026 (PORTALS_PLAN.md, "Organization reports"). **Steps 1 and 2 built 2 November 2026: Overview, Use, Members, Counselors and Quality.** Code: `App\Services\OrgReports`, `App\Support\ReportFigures`, `config/org-reports.php`, views in `resources/views/organizations/reports/`. Tests: `OrgReportsTest`.

## The rules (all pages)
- **Counts only.** No name of a member or a counselor, nothing from the chat, nothing from a counselor's notes. Only the organization admin's own organization (`bookings.org_id`, `memberships.org_id`).
- **Small numbers are hidden** (`ReportFigures`, minimum group 5): a number from 1 to 4 is shown as "fewer than 5". In a set of figures that add up to a total, if only one cell is hidden the smallest other cell is hidden too ("hidden"), so nobody can subtract to find it. A comparison or a percentage is shown only when the groups are large enough.
- **Period:** this month, last 3 months (the default) or last 12 months, by Malaysian calendar month. Weeks (Monday) for up to 3 months, months for 12. Sessions are counted by the day they take place.
- **Not cached** by the browser. Every figure has a short sentence saying what it counts.

## Built
- **Overview:** active members, waiting for approval, members who left in the period, counselors, and sessions in the period against the same number of days just before (the change is shown only when both are 5 or more). Cancelled sessions are not counted.
- **Use:** sessions over time, how sessions ended (completed, no-show, cancelled, still ahead or not marked yet), online or in person, busiest weekdays, busiest hours, how far ahead people book (same day, 1 to 2, 3 to 7, more than a week), and time requests (how they ended, how many answered, and the share answered within 24 hours when at least 5 were answered).
- **Members:** new members and members who left over time, how new members joined (e-mail domain, reference code, invitation, other), and the share of active members who have booked at least once (shown from 5 active members; a cancelled booking does not count).

- **Counselors:** hours offered (times opened for this organization in the period) against hours booked, and per counselor: hours offered, sessions, the share booked, and the first free time (the next time still open, and how many days away). A counselor's name is shown (the organization chose them), a client never is. The sessions of one counselor are a small group: the number follows the same hiding rule, and the share booked is shown only when it is not hidden. In an organization with few counselors one small number can hide its neighbour too (the rule that stops subtraction).
- **Quality:** the average stars of the reviews of this organization's sessions (overall, and per counselor on their own count), shown from 5 reviews (`config/reviews.php`, `min_for_average`); never a comment. The cancelling rate (from 5 sessions) and who cancelled (the member, the counselor, MyKaunsel or the organization). A removed review is not counted. Reviews exist only once clients can write them (REVIEWS.md), so until then this page says "Not enough reviews yet".

## Still to build
- **Export** to CSV and PDF with the same hiding rules, for the chosen period, and **comparing two periods**.
- **By unit or department**, shown only when the group is large enough (the units are not in use yet).
