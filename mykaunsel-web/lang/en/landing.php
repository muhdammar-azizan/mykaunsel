<?php

/*
 * The public website in English. The same keys, in Malay (the default), are in lang/ms/landing.php: change both together.
 * Facts only: no made-up numbers, names or logos. What is a placeholder is listed in config/landing.php (placeholder_blocks).
 */
return [
    'meta' => [
        'title' => 'MyKaunsel — Verified counseling support',
        'description' => 'Find a registered counselor and book a session online or in person. For individuals, organizations and counselors in Malaysia.',
        'soon_title' => 'Coming soon — MyKaunsel',
    ],

    'a11y' => ['skip' => 'Skip to content', 'menu' => 'Menu', 'open_menu' => 'Open menu', 'close_menu' => 'Close menu', 'main_nav' => 'Main navigation'],

    'nav' => [
        'platform' => 'Platform', 'solutions' => 'Solutions', 'how_it_works' => 'How it works', 'pricing' => 'Pricing', 'resources' => 'Resources',
        'login' => 'Log in', 'signup' => 'Sign up', 'signup_org' => 'As an organization', 'signup_counselor' => 'As a counselor',
        'waitlist' => 'Join the waitlist', 'get_app' => 'Get the app', 'soon' => 'Soon', 'see_all' => 'See all', 'more' => 'More',
    ],

    'menu' => [
        'platform' => [
            'columns' => [
                ['title' => 'For individuals', 'items' => [
                    ['find-a-counselor', 'Find a counselor', 'Browse registered counselor profiles'],
                    ['book-a-session', 'Book a session', 'Pick a free time and confirm'],
                    ['online-in-person', 'Online and in person', 'Choose the way you are comfortable with'],
                    ['invite-code', 'Join with an invite code', 'Use the code from your organization'],
                ]],
                ['title' => 'For counselors', 'items' => [
                    ['shared-calendar', 'Shared calendar', 'One schedule for every place you work'],
                    ['booking-modes', 'Booking modes', 'Instant booking or time requests'],
                    ['meeting-links', 'Meeting links', 'Set up the link for online sessions'],
                    ['profile-credentials', 'Profile and credentials', 'Show your specializations and languages'],
                ]],
                ['title' => 'For organizations', 'items' => [
                    ['member-management', 'Member management', 'Accept and manage your members'],
                    ['domain-verification', 'Domain verification', 'Verify your organization with DNS'],
                    ['counselor-roster', 'Counselor roster', 'Invite and manage your counselors'],
                    ['reports-analytics', 'Reports and analytics', 'Counts only, never names'],
                ]],
                ['title' => 'Safety', 'items' => [
                    ['counselor-verification', 'Counselor verification', 'A records check plus a human review'],
                    ['privacy-data', 'Privacy and data', 'What we keep and who can see it'],
                    ['session-policies', 'Session policies', 'Booking and cancellation rules'],
                    ['ai-assistant', 'AI assistant', 'A companion, not a counselor'],
                ]],
            ],
            'more' => [
                'items' => [['get-the-app', 'Get the app', 'iOS and Android'], ['wellness-feed', 'Wellness feed', 'Share and support']],
                'card' => ['title' => 'Download the app', 'text' => 'Book a session right from your phone.', 'cta' => 'Get the app', 'slug' => 'get-the-app'],
            ],
            'see_all' => ['features', 'See all features'],
        ],
        'solutions' => [
            'columns' => [
                ['title' => 'Universities', 'items' => [
                    ['universities', 'Campus counseling', 'Support for your students'],
                    ['student-codes', 'Student invite codes', 'Students join with one code'],
                    ['alumni-access', 'Alumni access', 'Keep the support after graduation'],
                ]],
                ['title' => 'Corporate', 'items' => [
                    ['corporate', 'Employee wellbeing', 'Support for your team'],
                    ['closed-access', 'Closed access', 'Only your employees can book'],
                    ['aggregate-reports', 'Aggregate reports', 'Counts only, never names'],
                ]],
                ['title' => 'Clinics', 'items' => [
                    ['clinics', 'Open listing', 'Your clinic can be found by the public'],
                    ['clinic-counselors', 'Clinic counselors', 'Manage counselors in one place'],
                    ['clinic-commission', 'Commission model', 'How the cost works for clinics'],
                ]],
                ['title' => 'Independent counselors', 'items' => [
                    ['independent-counselors', 'Practise on the platform', 'Take bookings without your own system'],
                    ['own-schedule', 'Your own schedule', 'You set the times that are open'],
                    ['build-a-profile', 'Build a profile', 'Show your specializations and languages'],
                ]],
            ],
            'more' => [
                'items' => [['how-it-works', 'How it works', 'Three paths, step by step'], ['pricing', 'Pricing', 'Plans and commission']],
                'card' => ['title' => 'Not sure which fits?', 'text' => 'Tell us about your organization.', 'cta' => 'Talk to us', 'slug' => 'contact'],
            ],
            'see_all' => ['solutions', 'See all solutions'],
        ],
        'how' => [
            'columns' => [
                ['title' => 'Individuals', 'items' => [
                    ['how-choose', 'Choose', 'Find a counselor that suits you'],
                    ['how-book', 'Book', 'Pick a time and confirm'],
                    ['how-meet', 'Meet', 'Online or in person'],
                ]],
                ['title' => 'Organizations', 'items' => [
                    ['how-apply-org', 'Apply', 'Register your organization'],
                    ['how-verify-domain', 'Verify your domain', 'Prove you own the domain'],
                    ['how-add-counselors', 'Add counselors', 'Invite your team'],
                ]],
                ['title' => 'Counselors', 'items' => [
                    ['how-apply-counselor', 'Apply', 'Send your details and practising certificate'],
                    ['how-get-verified', 'Get verified', 'A records check plus a human review'],
                    ['how-open-slots', 'Open slots', 'Set the times that can be booked'],
                ]],
            ],
            'more' => null,
            'see_all' => ['how-it-works', 'See how it works'],
        ],
        'pricing' => [
            'columns' => [
                ['title' => 'Plans', 'items' => [
                    ['plan-universities', 'Universities', 'A yearly plan by size'],
                    ['plan-corporate', 'Corporate', 'A yearly plan by size'],
                    ['compare-plans', 'Compare plans', 'A comparison table'],
                ]],
                ['title' => 'Commission', 'items' => [
                    ['commission-clinics', 'Clinics', 'A commission on each session'],
                    ['commission-independent', 'Independent counselors', 'A commission on each session'],
                    ['how-commission-works', 'How commission works', 'A short explanation'],
                ]],
                ['title' => 'For individuals', 'items' => [
                    ['pricing-individuals', 'No platform fee', 'Individuals pay no platform fee'],
                ]],
            ],
            'more' => [
                'items' => [['promo-codes', 'Promo codes', 'How to use a code'], ['request-a-quote', 'Request a quote', 'For larger organizations'], ['pricing-faq', 'Pricing FAQ', 'Questions about payment']],
                'card' => ['title' => 'Not sure which plan?', 'text' => 'Talk to us.', 'cta' => 'Talk to us', 'slug' => 'contact'],
            ],
            'see_all' => ['pricing', 'See all pricing'],
        ],
        'resources' => [
            'columns' => [
                ['title' => 'Learn', 'items' => [
                    ['faq-help', 'FAQ and Help', 'Answers to common questions'],
                    ['mental-health-articles', 'Mental health articles', 'Short reads'],
                    ['glossary', 'Glossary', 'Terms in plain language'],
                ]],
                ['title' => 'Support', 'items' => [
                    ['contact', 'Contact', 'We are here to help'],
                    ['safety-crisis', 'Safety and crisis support', 'Urgent help lines'],
                    ['status', 'Status', 'How the system is doing'],
                ]],
                ['title' => 'Company', 'items' => [
                    ['about', 'About', 'Our mission and project'],
                    ['terms', 'Terms of Service', 'The rules for using MyKaunsel'],
                    ['privacy', 'Privacy and PDPA', 'How your data is looked after'],
                ]],
            ],
            'more' => null,
            'see_all' => ['faq-help', 'See the help center'],
        ],
    ],

    'hero' => [
        'strong' => 'Verified counseling support.',
        'soft' => 'Find a registered counselor and book a session online or in person, right from your phone.',
        'app_store' => 'App Store', 'google_play' => 'Google Play', 'badge_soon' => 'Coming soon',
        'media_alt' => 'The MyKaunsel app',
    ],

    'paths' => [
        'individual' => ['label' => 'Individual', 'title' => 'I am looking for support', 'text' => 'Find a registered counselor and book a session in the app.', 'cta' => 'Get the app'],
        'organization' => ['label' => 'Organization', 'title' => 'We are an organization', 'text' => 'Manage counselors, members and bookings in one portal.', 'cta' => 'Sign up as an organization', 'cta_closed' => 'Join the waitlist'],
        'counselor' => ['label' => 'Counselor', 'title' => 'I am a counselor', 'text' => 'Take bookings without building your own system.', 'cta' => 'Sign up as a counselor', 'cta_closed' => 'Join the waitlist'],
    ],

    'proof' => [
        'strong' => 'Built on trust.',
        'soft' => 'Every part of MyKaunsel is designed to look after your safety and privacy.',
        'assurances' => [
            ['title' => 'Credentials checked', 'text' => 'Counselors are checked against registration records and reviewed by a person before a profile is shown.'],
            ['title' => 'Book in a few taps', 'text' => 'Pick a free time and book in a few taps.'],
            ['title' => 'Sessions are not recorded', 'text' => 'MyKaunsel does not record your sessions with a counselor.'],
        ],
        'logos_label' => 'Trusted by', 'logo' => 'Logo',
        'quotes' => [
            ['text' => 'We can offer counseling support to students in a much more organized way.', 'name' => 'Sample Name', 'role' => 'Counseling officer, university'],
            ['text' => 'I no longer have to manage my schedule and bookings by hand.', 'name' => 'Sample Name', 'role' => 'Registered counselor'],
        ],
        'figures' => [['00+', 'verified counselors'], ['00+', 'organizations'], ['00', 'sessions this month']],
        'placeholder_note' => 'Sample content',
    ],

    'problem' => [
        'strong' => 'Finding support should not be hard.',
        'soft' => 'Too many people stop before they even start.',
        'cards' => [
            ['title' => 'Hard to know who is qualified', 'text' => 'It is not easy to be sure a counselor is registered and qualified.'],
            ['title' => 'Hard to find and book', 'text' => 'Finding a suitable time usually takes many steps.'],
            ['title' => 'Worried about privacy', 'text' => 'Worrying about who will find out often stops the first step.'],
        ],
        'learn_more' => 'Learn more',
    ],

    'solution' => [
        'strong' => 'One platform you can trust.',
        'soft' => 'Verified counselors, simple booking and privacy that is looked after.',
        'pillars' => [
            ['slug' => 'counselor-verification', 'media' => 'solution-verified', 'title' => 'Verified counselors.', 'text' => 'Every counselor is checked against registration records and reviewed by a person.', 'cta' => 'About verification'],
            ['slug' => 'book-a-session', 'media' => 'solution-booking', 'title' => 'Simple booking.', 'text' => 'Filter by specialization, language and place, then pick a free time.', 'cta' => 'See how to book'],
            ['slug' => 'privacy-data', 'media' => 'solution-private', 'title' => 'Private and safe.', 'text' => 'You control what is shared. Organizations do not read your conversations.', 'cta' => 'Read about privacy'],
        ],
    ],

    'how' => [
        'strong' => 'Three steps to get started.',
        'soft' => 'From finding a counselor to meeting them, all in one app.',
        'steps' => [
            ['title' => 'Choose', 'text' => 'Filter counselors by specialization, language and place.'],
            ['title' => 'Book', 'text' => 'Pick a free time and confirm your booking.'],
            ['title' => 'Meet', 'text' => 'Join the session online or attend in person.'],
        ],
        'cta' => 'See how it works',
    ],

    'audiences' => [
        'org' => [
            'label' => 'For organizations', 'strong' => 'One portal for your whole counseling unit.', 'soft' => 'Universities, companies and clinics.',
            'benefits' => ['Invite and manage your counselors', 'Accept members with an invite code', 'See count-only reports, never names', 'Verify your organization with DNS'],
            'cta' => 'Sign up as an organization', 'cta_closed' => 'Join the waitlist', 'learn' => 'Learn more',
        ],
        'counselor' => [
            'label' => 'For counselors', 'strong' => 'Your practice, your schedule.', 'soft' => 'Take bookings without your own system.',
            'benefits' => ['One calendar for every place you work', 'Instant booking or time requests', 'Meeting links for online sessions', 'A profile with your specializations and languages'],
            'cta' => 'Sign up as a counselor', 'cta_closed' => 'Join the waitlist', 'learn' => 'Learn more',
        ],
    ],

    'app' => [
        'strong' => 'Take MyKaunsel with you.',
        'soft' => 'Find, book and meet a counselor right from your phone.',
        'scan' => 'Scan to download', 'qr_label' => 'QR code', 'phone_alt' => 'The MyKaunsel app screen',
    ],

    'faq' => [
        'title' => 'Frequently asked questions', 'cta' => 'See all questions',
        'items' => [
            ['q' => 'Is MyKaunsel a replacement for professional help?', 'a' => 'No. MyKaunsel helps you reach a registered counselor. It is not a replacement for emergency or professional care. If you are in danger, call one of the help lines at the bottom of this page.'],
            ['q' => 'Who can see my conversations?', 'a' => 'Your conversation with the AI assistant is private and is only kept if you agree. It is not shared with counselors or with your organization.'],
            ['q' => 'How are counselors verified?', 'a' => 'Counselors are checked against registration records and their documents, then reviewed by our team before a profile is shown.'],
            ['q' => 'Who can book which counselor?', 'a' => 'Independent counselors and open clinics can be booked by anyone. Counselors of a closed organization, such as a university or a company, can only be booked by members of that organization.'],
            ['q' => 'Is the AI assistant a counselor?', 'a' => 'No. It is a companion that helps you put your feelings in order and find a suitable counselor. It does not diagnose or treat.'],
        ],
    ],

    'cta' => ['strong' => 'Get started today.', 'soft' => 'Choose the path that suits you.'],

    'urgent' => [
        'label' => 'Need help right now?',
        'text' => 'MyKaunsel is not a replacement for emergency or professional care. If you or someone else is in danger, call:',
    ],

    'footer' => [
        'description' => 'Verified counseling support for Malaysia.',
        'columns' => [
            ['title' => 'Platform', 'items' => [['for-individuals', 'For individuals'], ['for-counselors', 'For counselors'], ['for-organizations', 'For organizations'], ['features', 'Features'], ['find-a-counselor', 'Find a counselor'], ['get-the-app', 'Get the app'], ['wellness-feed', 'Wellness feed']]],
            ['title' => 'Solutions', 'items' => [['universities', 'Universities'], ['corporate', 'Corporate'], ['clinics', 'Clinics'], ['independent-counselors', 'Independent counselors']]],
            ['title' => 'Pricing', 'items' => [['pricing', 'Plans'], ['commission', 'Commission'], ['promo-codes', 'Promo codes'], ['pricing-faq', 'Pricing FAQ']]],
            ['title' => 'Resources', 'items' => [['how-it-works', 'How it works'], ['faq-help', 'FAQ and Help'], ['mental-health-articles', 'Mental health articles'], ['glossary', 'Glossary'], ['safety-crisis', 'Safety and crisis support'], ['status', 'Status']]],
            ['title' => 'Company', 'items' => [['about', 'About'], ['contact', 'Contact'], ['join-waitlist', 'Join the waitlist']]],
            ['title' => 'Legal', 'items' => [['terms', 'Terms of Service'], ['privacy', 'Privacy Policy'], ['pdpa-notice', 'PDPA notice'], ['verification-policy', 'Counselor verification policy'], ['session-policy', 'Session and cancellation policy']]],
        ],
        'copyright' => '© :year MyKaunsel. All rights reserved.',
        'contact' => 'Contact', 'language' => 'Language', 'sitemap' => 'Sitemap', 'ms' => 'Bahasa Melayu', 'en' => 'English',
    ],

    'soon' => [
        'strong' => 'This page is coming soon.', 'soft' => 'We are getting it ready.',
        'back' => 'Back to the home page', 'pages' => [],
    ],
];
