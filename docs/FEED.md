# Wellness Feed

A small public timeline for everyone who is signed in (individuals, clients, counselors). Text only, no AI.

## Rules (server)

- **Posts are ordinary, under the author's own name** (the name in their profile), like Twitter or Threads. There is **no anonymous posting** for now; it is deliberately left out until the owner decides.
- A verified counselor (one clients can book) carries a `counselor` badge on posts and comments.
- Post and comment: text only, at most **500 characters** (`config/feed.php`). A person can post at most **20 a day** (Malaysian day) and write at most 10 a minute / 60 an hour (posts, comments, edits, reports, mutes share one limit; likes have their own).
- Writing is safe to repeat: `client_id` (UUID made by the app) stores a retried post or comment once. Liking twice is one like. Deleting twice and reporting twice change nothing more.
- **Crisis words** (same rules as the chat, `CrisisDetector`) never stop a post or a comment. The answer carries `crisis: true` and the `helplines`, and the app shows the calm help card.
- Edit and delete: only the author. Edit sets `edited`. Delete is a soft delete. A moderator's removal (`removed_at`, from the Moderate Feed page, later) hides it the same way. A post or comment that is not visible is `404` for everyone, like one that never existed.
- **Report**: five reasons (`spam`, `harassment`, `self_harm`, `inappropriate`, `other`). One person reports one post or comment once. Own writing cannot be reported (`409 cannot_report_own`). Reports wait in `feed_reports` (`status` pending) for the Moderate Feed queue.
- **Mute**: hides one person's posts and comments from the muter's own feed and comment lists only. Done from a post (`mute-author`), listed and undone in the muted people list.
- Ids in the API are 26 character public ids. No internal id and no e-mail is ever returned.

## API (all need a signed-in, verified person)

| Method | Path | |
|---|---|---|
| GET | `/feed?cursor=` | Newest first, 20 a page. `next_cursor` is the last post's id or null. |
| POST | `/feed/posts` | `text`, `client_id` → `201` (`200` if repeated) `{post, crisis, helplines}` |
| GET / PATCH / DELETE | `/feed/posts/{id}` | One post / edit own (`text`) / take own down |
| PUT / DELETE | `/feed/posts/{id}/reaction` | Like / take the like back → `{reactions: {count, mine}}` |
| GET / POST | `/feed/posts/{id}/comments` | Oldest first with `cursor` / comment (`text`, `client_id`) → `{comment, crisis, helplines}` |
| DELETE | `/feed/comments/{id}` | Delete own comment |
| POST | `/feed/posts/{id}/report`, `/feed/comments/{id}/report` | `reason` → `202` |
| POST | `/feed/posts/{id}/mute-author` | → `{mute: {id, name}}` |
| GET / DELETE | `/feed/mutes`, `/feed/mutes/{id}` | Muted people / unmute |

A post: `{id, text, created_at, edited, author: {name, counselor}, mine, reactions: {count, mine}, comments_count}`.
A comment: `{id, text, created_at, author: {name, counselor}, mine}`.

Refusals carry `code`: `post_not_found`, `comment_not_found`, `mute_not_found` (404), `cannot_report_own`, `cannot_mute_self` (409), `daily_post_limit` (429, `Retry-After`), `text_empty` (422).

## The app

The Feed tab (`features/feed`): the feed with pull-to-refresh and paging, a New post screen (live count to 500, says it posts under the person's name, also used to edit), the post page with comments and the comment box at the bottom, the post menu (edit/delete for the owner, report with five reasons and mute for others), a Muted people list (the volume icon in the Feed's bar), and the calm help card after a flagged post or comment. A post or comment that did not go stays on screen and goes again with the same client id.

## Not built yet

- Moderate Feed (Platform Admin web): the queue of `feed_reports`, dismiss / remove / warn.
- Community e-mail toggle (there are no feed e-mails).
- Anonymous posting (owner decision pending).
