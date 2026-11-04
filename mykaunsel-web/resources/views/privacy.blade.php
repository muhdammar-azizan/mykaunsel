@php
    $tbc = fn (string $what) => new \Illuminate\Support\HtmlString('<mark class="rounded bg-yellow-100 px-1">'.e(__('To be confirmed')).': '.e($what).'</mark>');
    $support = config('api.support_email');
@endphp

<x-legal-page :title="__('Privacy Policy')">
    <p>{{ __('This page explains what MyKaunsel keeps about you, why, who can see it, how long we keep it, and what you can do about it. We follow the Personal Data Protection Act 2010 of Malaysia (PDPA).') }}</p>
    <p>{{ __('Who we are') }}: {{ $tbc(__('the legal name and address of the organization that runs MyKaunsel')) }}. {{ __('Contact for questions about your data') }}: @if ($support)<a href="mailto:{{ $support }}">{{ $support }}</a>@else {{ $tbc(__('the support e-mail address')) }}@endif.</p>

    <h2>{{ __('What we keep') }}</h2>
    <ul>
        <li>{{ __('Your account: name, e-mail address, a scrambled form of your password (we can not read it), your time zone, and a photo if you add one.') }}</li>
        <li>{{ __('Your bookings and time requests: which counselor, when, online or in person, and the status.') }}</li>
        <li>{{ __('Your organization: which organization you belong to (or asked to join) and how you joined.') }}</li>
        <li>{{ __('The chat, only if you agree to it: the conversations you have with the assistant.') }}</li>
        <li>{{ __('The Feed: your posts, comments and likes, shown under your own name, and any reports you make.') }}</li>
        <li>{{ __('The phones signed in to your account (the phone model and when it was last used) and a record of important actions, such as signing in or changing your password.') }}</li>
        <li>{{ __('E-mails we send you about your bookings and account, unless you switch the booking ones off.') }}</li>
    </ul>

    <h2>{{ __('The chat with the assistant') }}</h2>
    <ul>
        <li>{{ __('The chat is an AI companion, not a counselor. You must agree before you can use it. You can say no and still book counselors, and you can withdraw your agreement at any time in Account.') }}</li>
        <li>{{ __('Only the words of the conversation (the latest messages) are sent to the AI service so it can answer. Your name, e-mail address and account are never sent. E-mail addresses, web links, identity card numbers and phone numbers you type are replaced before sending.') }}</li>
        <li>{{ __('The AI service is provided by Anthropic, a company outside Malaysia, so the words of your chat leave Malaysia to be answered.') }} {{ $tbc(__('where the provider keeps the words and for how long, once the contract is final')) }}</li>
        <li>{{ __('We check what you write with fixed rules for words that suggest you may be in danger. If they match, the app shows phone numbers you can call. This is not a decision about you, and it never stops you from writing.') }}</li>
        <li>{{ __('Counselors and organizations can not read your chat.') }}</li>
        <li>{{ __('Conversations are deleted :months months after your last message.', ['months' => config('chat.retention_months')]) }}</li>
    </ul>

    <h2>{{ __('Who can see what') }}</h2>
    <ul>
        <li>{{ __('A counselor you book sees your name as you gave it and the details of your sessions with them. A counselor may write a private note about a session. It is stored encrypted and only that counselor can read it: not the organization, not the MyKaunsel team, and it is never shown to you in the app. You can ask for access to it under the PDPA.') }}</li>
        <li>{{ __('The people who run an organization you belong to can see your name and e-mail address as a member, when you joined, and that you left. They can not read your chat.') }}</li>
        <li>{{ __('Your Feed posts and comments can be read by everyone signed in to MyKaunsel, under your name. If someone reports one, the MyKaunsel team reads it to decide what to do. The person who reported it is not shown to you.') }}</li>
        <li>{{ __('If you report a counselor, the MyKaunsel team keeps who reported, as part of the record of the complaint. Nobody else sees it, not the counselor and not the organization.') }}</li>
        <li>{{ __('The small team that runs MyKaunsel can see account details to keep the service working and safe. Their important actions are recorded in an audit log.') }}</li>
        <li>{{ __('We do not sell your information, and we do not use it for advertising.') }}</li>
    </ul>

    <h2>{{ __('How long we keep it') }}</h2>
    <ul>
        <li>{{ __('Chat conversations: :months months after the last message.', ['months' => config('chat.retention_months')]) }}</li>
        <li>{{ __('Notices inside the app: :read days after you read them, :unread days if you never do.', ['read' => config('notifications.retention.read_days'), 'unread' => config('notifications.retention.unread_days')]) }}</li>
        <li>{{ __('Complaints: the words of a complaint are erased :years years after it is closed. The record that it happened (reference, dates, outcome) stays.', ['years' => config('complaints.retention.closed_years')]) }}</li>
        <li>{{ __('Counselor session notes: :years years after the session, then they are erased.', ['years' => config('session-notes.retention_years')]) }}</li>
        <li>{{ __('Booking records: while your account exists. After you delete your account, :days days (see below).', ['days' => config('account.deleted_booking_days')]) }}</li>
        <li>{{ __('The e-mail address on the sign-up reminder list: erased once the reminder has been sent.') }}</li>
    </ul>

    <h2>{{ __('If you are a counselor') }}</h2>

    <h3>{{ __('What we keep about counselors') }}</h3>
    <ul>
        <li>{{ __('Your KB number, your PA number and when it is valid.') }}</li>
        <li>{{ __('The documents you send to apply: your KB certificate, your PA certificate and a copy of your ID or passport.') }}</li>
        <li>{{ __('The result of comparing your details with the registration records we hold. These records are a copy that we keep; we do not have a live connection to a government system. Our team reviews every application.') }}</li>
        <li>{{ __('The history and notes of the review of your application.') }}</li>
        <li>{{ __('The times you open, your weekly hours, your blocked times (and the note you may add to one), and the requests and bookings you receive.') }}</li>
        <li>{{ __('Reviews and complaints about you.') }}</li>
        <li>{{ __('A photo, if you add one to your own account. It is never shown to clients.') }}</li>
        <li>{{ __('The choices you make on your profile: the names clients see, your languages and your specializations.') }}</li>
    </ul>

    <h3>{{ __('Who can see counselor data') }}</h3>
    <ul>
        <li>{{ __('You need to be signed in with a confirmed e-mail address to find a counselor. A counselor who works for a closed organization is shown only to that organization\'s members.') }}</li>
        <li>{{ __('Clients see the name you choose, your languages, your specializations and the times you open. They do not see your photo, your KB or PA number, your e-mail address or your documents.') }}</li>
        <li>{{ __('The people who run an organization you work for can see your name, whether your practising certificate is valid or about to expire, and counts of your sessions. They see the stars clients give, never a comment, and never what a client said or your private notes.') }}</li>
        <li>{{ __('The MyKaunsel team that reviews applications sees your documents and details. Every time a document is opened, it is recorded.') }}</li>
        <li>{{ __('Reviews written about you are shown to you without the client\'s name. If a client complained about you, you are not shown who.') }}</li>
        <li>{{ __('Your private session notes can be read only by you.') }}</li>
    </ul>

    <h3>{{ __('How long we keep counselor data') }}</h3>
    <ul>
        <li>{{ __('The copy of your ID or passport: :days days after the final decision on your application.', ['days' => config('counselors.retention.ic_days')]) }}</li>
        <li>{{ __('Certificates of an application that was rejected: :days days after the rejection.', ['days' => config('counselors.retention.rejected_certificates_days')]) }}</li>
        <li>{{ __('Certificates of an approved counselor: while your account exists.') }}</li>
        <li>{{ __('Session notes: :years years after the session, then they are erased.', ['years' => config('session-notes.retention_years')]) }}</li>
        <li>{{ __('The words of a complaint about you: :years years after it is closed. The record that it happened stays.', ['years' => config('complaints.retention.closed_years')]) }}</li>
        <li>{{ __('Notices inside the portal: :read days after you read them, :unread days if you never do.', ['read' => config('notifications.retention.read_days'), 'unread' => config('notifications.retention.unread_days')]) }}</li>
        <li>{{ __('Records of important actions (the audit log). They hold numbers and counts, never a client\'s name or what was written in a note.') }} {{ $tbc(__('how long records of important actions are kept, with a lawyer')) }}</li>
    </ul>

    <h3>{{ __('Your rights as a counselor') }}</h3>
    <p>{{ __('You can see and correct what you can change yourself: the names clients see, your languages and specializations, and your account details. Your KB number, PA number, PA date and status come from your application and our checks; the MyKaunsel team changes them, so write to us if one is wrong. You can ask for a copy of your data and withdraw an agreement you gave.') }} {{ $tbc(__('the contact to write to and how long we take to answer')) }}</p>

    <h3>{{ __('Session notes when a counselor leaves') }}</h3>
    <p>{{ $tbc(__('what happens to a counselor\'s private session notes if the counselor leaves an organization or MyKaunsel')) }}</p>

    <h2>{{ __('Deleting your account') }}</h2>
    <p>{{ __('You can delete your account yourself in the app (Account, then Delete account). It can not be undone.') }}</p>
    <ul>
        <li><strong>{{ __('Erased:') }}</strong> {{ __('your name, e-mail address and photo, your chats with the assistant, your Feed posts and comments, your likes, and every sign-in of yours.') }}</li>
        <li><strong>{{ __('Ended:') }}</strong> {{ __('all your later sessions are cancelled and the counselors are told. If you belong to an organization, you leave it and its administrators are told your name.') }}</li>
        <li><strong>{{ __('Kept:') }}</strong> {{ __('records of past bookings stay for :days days without your name, and then they are deleted. What a counselor or an organization wrote about a session stays with them, and so does any complaint that depends on it, still without your name. If you reported a counselor, the fact that you did stays on record.', ['days' => config('account.deleted_booking_days')]) }}</li>
        <li>{{ __('Counselors and administrators can not delete their account in the app, because other people depend on their records. They write to support.') }}</li>
    </ul>

    <h2>{{ __('Your rights') }}</h2>
    <p>{{ __('You may ask to see the personal data we hold about you, to correct it, to withdraw an agreement you gave, and to have it deleted. Most of this you can do yourself in Account: change your name, e-mail and photo, withdraw the chat agreement, switch off booking e-mails, and delete your account. For anything else, such as a copy of your data, write to us.') }} @if ($support)<a href="mailto:{{ $support }}">{{ $support }}</a>@else {{ $tbc(__('the support e-mail address')) }}@endif. {{ __('You may also complain to the Personal Data Protection Commissioner of Malaysia.') }}</p>

    <h2>{{ __('Cookies and sign-in on the website') }}</h2>
    <p>{{ __('The website uses only the cookies it needs to keep you signed in and to protect forms. We do not use advertising or tracking cookies. Its pages load their text style from Google Fonts, so Google can see that your device asked for it.') }}</p>

    <h2>{{ __('Signing in with Google') }}</h2>
    <p>{{ __('On the website you can choose to sign in with Google. Google sends us your e-mail address (and whether Google has verified it), your name and the address of your Google picture. We use only the e-mail address, to find the MyKaunsel account that already has it, and we do not keep the rest: signing in with Google never creates an account. We do not receive your Google password. If your account uses two-factor sign-in, it is still asked for.') }} {{ $tbc(__('whether Google sign-in stays available to counselors and administrators; today it works for any existing account')) }}</p>

    <h2>{{ __('If something goes wrong with your data') }}</h2>
    <p>{{ __('If personal data we hold about you is lost or seen by people who should not see it, we will tell the people affected and, where the law requires it, the authorities.') }} {{ $tbc(__('the exact notification process and timeline, with a lawyer')) }}</p>

    <h2>{{ __('Children') }}</h2>
    <p>{{ $tbc(__('the minimum age, and what happens for people under it')) }}</p>

    <h2>{{ __('The sign-up reminder list') }}</h2>
    <p>{{ __('If you ask us to tell you when a sign-up reopens, we keep your e-mail address only for that. We send one e-mail, we do not share your address, and every e-mail has a link to opt out. Addresses are deleted automatically after the e-mail has been sent.') }}</p>

    <h2>{{ __('Changes') }}</h2>
    <p>{{ __('When this page changes in a way that matters, we will tell you in the app or by e-mail before it starts to apply.') }}</p>

    <h2>{{ __('If you are in danger') }}</h2>
    <p>{{ __('If you or someone you know may be in danger right now, please call') }}
        @foreach (config('chat.helplines') as $line){{ $line['name'] }} <a href="tel:{{ $line['number'] }}">{{ $line['number'] }}</a>@if (! $loop->last), @endif @endforeach.</p>
</x-legal-page>
