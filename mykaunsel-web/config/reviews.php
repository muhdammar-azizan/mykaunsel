<?php

/*
 * Client reviews of counselors (docs/REVIEWS.md). Each number and word list is a decision that can be changed here.
 */
return [
    // A stars average is shown (to the public, and by organization) only for a counselor with at least this many reviews.
    'min_for_average' => 5,

    // A review opens the day after the session, stays open this many days after it, and can be edited for this many days after it was written.
    'window_days' => 30,
    'edit_days' => 7,

    // A session that is over, still unmarked by the counselor after this many hours, may be reviewed anyway ("attendance not confirmed").
    'unconfirmed_after_hours' => 48,

    'comment_max' => 1000,
    'reply_max' => 1000,

    // The few tags a client may tick (about the experience, never "is this counselor good").
    'tags' => [
        'listened' => 'Felt listened to',
        'comfortable' => 'Felt comfortable',
        'helpful' => 'Helpful',
        'on_time' => 'On time',
    ],

    // Why a counselor may flag a review for the MyKaunsel team.
    'flag_reasons' => [
        'abusive' => 'Abusive or threatening',
        'false' => 'False or about something that did not happen',
        'wrong_counselor' => 'About another counselor',
        'other' => 'Another reason',
    ],

    // A comment with any of these (lower case, whole words or phrases) is NOT shown to anyone until the MyKaunsel team has read it, and the
    // person is shown the helpline numbers. A starting list in English and Malay: the owner reviews it.
    'danger_phrases' => [
        'suicide', 'suicidal', 'kill myself', 'end my life', 'want to die', 'hurt myself', 'self harm', 'self-harm', 'overdose',
        'bunuh diri', 'tamatkan nyawa', 'mencederakan diri', 'nak mati', 'tak nak hidup',
        'abuse', 'abused', 'assault', 'assaulted', 'rape', 'raped', 'harass', 'harassed', 'molest', 'touched me', 'threatened me', 'unsafe',
        'dera', 'didera', 'cabul', 'rogol', 'ugut', 'diugut', 'gangguan seksual',
    ],

    'per_page' => 20,
];
