<?php

/*
 * The wellness Feed (docs/FEED.md): text only, open to every signed-in person, no AI.
 */
return [

    // Characters in a post, and in a comment.
    'max_post_chars' => (int) env('FEED_MAX_POST_CHARS', 500),
    'max_comment_chars' => (int) env('FEED_MAX_COMMENT_CHARS', 500),

    // Posts one person may make in a day (Malaysian day), and the page size of the feed and of the comments.
    'daily_posts' => (int) env('FEED_DAILY_POSTS', 20),
    'per_page' => 20,

    // Limits per person: writing (posts, comments, edits, reports, mutes), and reacting.
    'write_rate_per_minute' => (int) env('FEED_WRITE_RATE_PER_MINUTE', 10),
    'write_rate_per_hour' => (int) env('FEED_WRITE_RATE_PER_HOUR', 60),
    'react_rate_per_minute' => (int) env('FEED_REACT_RATE_PER_MINUTE', 60),

    // Why someone reports a post or a comment (the moderators see this word).
    'report_reasons' => ['spam', 'harassment', 'self_harm', 'inappropriate', 'other'],
];
