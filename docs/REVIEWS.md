# Reviews of counselors

Design decided 31 October 2026 (see PORTALS_PLAN.md, "Reviews"). **First part built 2 November 2026: the rules on the server and the counselor's Reviews page.** The client's screen in the app, the MyKaunsel team's moderation pages and the weekly summary are NOT built yet (see the end).

Code: `App\Services\ReviewService`, `App\Models\Review`, `config/reviews.php`. Tests: `CounselorReviewsTest`.

## The client's rules (built in `ReviewService::submit` / `edit`; no endpoint or app screen yet)
- One review per session, only the client's own. Stars 1 to 5, optional tags (Felt listened to, Felt comfortable, Helpful, On time) and an optional comment (up to 1,000 characters).
- Only a **completed** session; or a confirmed one that is over and that the counselor has not marked within **48 hours** (stored as "attendance not confirmed"). Never a cancelled session or a no-show.
- It opens the **day after** the session (Malaysian day), closes **30 days** after it, and can be edited for **7 days** after it was written.
- In a comment, **phone numbers, e-mail addresses and identity card numbers are replaced by "[removed]"**.
- A comment with a **danger phrase** (list in `config/reviews.php`, English and Malay, to be reviewed by the owner) is **held**: the stars count at once, but the words are shown to nobody until the MyKaunsel team has read them. The caller is told, so the app can show the helpline numbers.

## The counselor's page (`/counselor/reviews`)
- Summary (average, count, bars per star) and every review newest first, **without the client's name or id** (no screen reads `client_user_id`). A held comment shows only "waiting for the MyKaunsel team to read it". The average is flagged as "public only from 5 reviews".
- **One public reply** per review, final (can not be edited). Not on a held review.
- **Flag as unfair** (abusive, false, about another counselor, other): once per review; it stays visible until the MyKaunsel team decides.
- Comment and reply are stored **encrypted**; the audit log records that a review was written, edited, replied to or flagged (ids only, never words). The page is never cached.
- Only the counselor's own reviews: another counselor's review is a 404. A review the team removed is not shown or counted.

## Not built yet
1. **The client's side:** an API endpoint to write and edit a review, "can I review?" on the booking, the review screen in the app (stars, tags, comment, the helpline numbers for a held comment), the reminder the day after the session, and the private box "Tell MyKaunsel privately" (it must go to the Platform Admin as a possible complaint, never to the counselor).
2. **The MyKaunsel team's pages (Platform Admin):** a queue of held comments (approve or remove) and of flags (look at both sides, remove only for breaking the guidelines, never for a low rating), an appeal for the counselor, and the flag for a burst of low ratings (review bombing).
3. **The weekly summary** to the counselor instead of one notification per review (decided).
4. **The organization's view:** only the overall average and, per counselor, an average from 5 reviews, never comments (belongs to the organization portal's Reviews and Reports pages).
5. **The public average on a counselor's profile** (from 5 reviews), and a later option of both sides confirming that a session took place.
