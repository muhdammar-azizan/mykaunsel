@php
    $tbc = fn (string $what) => new \Illuminate\Support\HtmlString('<mark class="rounded bg-yellow-100 px-1">'.e(__('To be confirmed')).': '.e($what).'</mark>');
@endphp

<x-legal-page :title="__('Terms of Use')">
    <p>{{ __('These terms are the rules for using MyKaunsel, the app and the website. By creating an account you agree to them. If you do not agree, please do not use MyKaunsel.') }}</p>
    <p>{{ __('MyKaunsel is run by') }} {{ $tbc(__('the legal name and address of the organization that runs MyKaunsel')) }}.</p>

    <h2>{{ __('What MyKaunsel is') }}</h2>
    <ul>
        <li>{{ __('A place to find counselors and book sessions with them, online or in person.') }}</li>
        <li>{{ __('A chat with an AI assistant, if you choose to use it. It is a companion, not a counselor, and it does not give diagnoses or advice about medicine.') }}</li>
        <li>{{ __('A Feed where people share short posts and support each other.') }}</li>
    </ul>
    <p><strong>{{ __('MyKaunsel is not an emergency service.') }}</strong> {{ __('If you or someone else may be in danger right now, call') }}
        @foreach (config('chat.helplines') as $line){{ $line['name'] }} <a href="tel:{{ $line['number'] }}">{{ $line['number'] }}</a>@if (! $loop->last), @endif @endforeach {{ __('or your nearest emergency number. Do not wait for a reply from the app.') }}</p>

    <h2>{{ __('Your account') }}</h2>
    <ul>
        <li>{{ __('Give true information, keep your password to yourself, and tell us if you think someone else is using your account.') }}</li>
        <li>{{ __('You are responsible for what is done with your account.') }}</li>
        <li>{{ __('As a client, you can belong to only one organization at a time. To join another one, leave the first. (Counselors can work for more than one organization: see "If you are a counselor".)') }}</li>
        <li>{{ __('You can delete your account in the app at any time (counselors and administrators write to support). What happens then is in the Privacy Policy.') }}</li>
    </ul>

    <h2>{{ __('Counselors and sessions') }}</h2>
    <ul>
        <li>{{ __('Counselors on MyKaunsel are independent professionals. We show the registration details they give us and check them against the registration records we hold, together with a review by our team, but a session is between you and the counselor.') }} {{ $tbc(__('the wording of what MyKaunsel is and is not responsible for')) }}</li>
        <li>{{ __('You can cancel or move a booking until the deadline shown when you book. The deadline is set by the organization the counselor works for. A session can be moved a limited number of times.') }}</li>
        <li>{{ __('If a session can not go ahead, for example because the counselor becomes unavailable, we tell you by e-mail.') }}</li>
        <li>{{ __('Sessions with an organization\'s counselors are for members of that organization. If you leave the organization, your later sessions with its counselors are cancelled, except those in the next few days, as the app tells you before you leave.') }}</li>
        <li>{{ __('Please come on time and be respectful.') }}</li>
    </ul>

    <h2>{{ __('If you are a counselor') }}</h2>

    <h3>{{ __('Who can be a counselor') }}</h3>
    <ul>
        <li>{{ __('Every counselor is approved by the MyKaunsel team before being shown to anyone. Counselors apply through the website and go through document and registration checks plus human review.') }}</li>
        <li>{{ __('To be approved you must give true registration details (your KB number, your PA number and when it is valid, and the documents we ask for). We check them against the registration records we hold, together with a review by our team. You must keep them true and tell us if anything changes.') }}</li>
        <li>{{ __('Your practising certificate (PA) must be valid for as long as you are listed.') }}</li>
        <li>{{ __('An organization can only invite a counselor that MyKaunsel has already approved. The organization does not verify your credentials.') }}</li>
        <li>{{ $tbc(__('the registration body and the exact professional requirements, with a lawyer')) }}</li>
    </ul>

    <h3>{{ __('You are independent') }}</h3>
    <p>{{ __('You are an independent professional, not an employee of MyKaunsel. A session is between you and the client. You are responsible for the advice and care you give and for keeping to the rules of your profession.') }} {{ $tbc(__('the wording of what MyKaunsel is and is not responsible for')) }}</p>

    <h3>{{ __('Your times and your clients') }}</h3>
    <ul>
        <li>{{ __('Open only times you can keep.') }}</li>
        <li>{{ __('You may cancel a session before it starts, at any hour, with a short reason that the client will see. Keep it kind, and do not write private details about yourself or the client.') }}</li>
        <li>{{ __('You can not move a booked session yourself: cancel it, and the client books again.') }}</li>
        <li>{{ __('The meeting link you add must be one you control. The client sees it :minutes minutes before the session starts.', ['minutes' => config('booking.meeting_link_visible_minutes')]) }}</li>
        <li>{{ __('Online sessions run on tools such as Google Meet, Zoom or Microsoft Teams. Those tools have their own terms and privacy rules, which apply to that part of the session.') }}</li>
    </ul>

    <h3>{{ __('Confidentiality') }}</h3>
    <p>{{ __('What a client tells you in a session is confidential. Do not record a session or share what was said, except as the law and your profession allow. Write session notes only for your own professional use: they stay private to you in MyKaunsel and are erased after :years years.', ['years' => config('session-notes.retention_years')]) }} {{ $tbc(__('the recording and confidentiality duties, and what a counselor must do if a client may be in danger, with a lawyer')) }}</p>

    <h3>{{ __('Emergencies') }}</h3>
    <p>{{ __('MyKaunsel is not an emergency service. If a client may be in danger, follow your professional duty and the law, and use the numbers on the Help page.') }}</p>

    <h3>{{ __('Organizations') }}</h3>
    <ul>
        <li>{{ __('An organization can invite you by e-mail. If you accept, you work for it as well as independently, and each time you open belongs to one of them.') }}</li>
        <li>{{ __('You may leave an organization at any time, and an organization may end your assignment at any time. What happens then is described below, under "If an organization ends your assignment".') }}</li>
    </ul>

    <h3>{{ __('Complaints and reviews') }}</h3>
    <p>{{ __('Clients can review a session after it, and anyone can send a complaint about a counselor. The MyKaunsel team looks at complaints and may set one aside, warn you, or suspend you. You can reply to a review, and you can report a review you believe breaks the rules. The person who complained is not shown to you.') }} {{ $tbc(__('the complaint procedure and how long a counselor has to answer')) }}</p>

    <h3>{{ __('Two different cases: suspension, and an organization ending your assignment') }}</h3>
    <p>{{ __('These are different, and they have different effects.') }}</p>

    <h4>{{ __('If your profile is suspended') }}</h4>
    <ul>
        <li>{{ __('Suspension is about you as a counselor, so it covers all the places you work, independent and organizations, at the same time.') }}</li>
        <li>{{ __('It happens automatically when your practising certificate (PA) has expired. We remind you :days days before it ends.', ['days' => implode(', ', (array) config('counselors.pa_reminder_days'))]) }}</li>
        <li>{{ __('The MyKaunsel team may also suspend you after a complaint, or if these terms are broken. We tell you why when we can.') }}</li>
        <li>{{ __('While you are suspended you are not listed, you can not open new times, and every request waiting for you is closed (the clients are told). The times you had already opened are not removed.') }}</li>
        <li>{{ __('Sessions already booked are kept when the suspension is automatic (the PA expired). When the team suspends you for another reason, it may decide to cancel your upcoming sessions; you and the clients are told.') }}</li>
        <li>{{ __('While suspended you can still sign in, look after the sessions already booked (cancel, mark, add a meeting link) and change your account.') }}</li>
        <li>{{ __('To lift a suspension for an expired PA, renew your PA and contact us. The team checks the registration records we hold.') }}</li>
    </ul>

    <h4>{{ __('If an organization ends your assignment') }}</h4>
    <ul>
        <li>{{ __('This applies only to that organization, and it works the same when you leave it yourself.') }}</li>
        <li>{{ __('Your later sessions with that organization are cancelled and the clients are told. Requests waiting for it are closed, and the clients are told.') }}</li>
        <li>{{ __('The times you opened for it that were never booked, and the weekly hours for it, are removed.') }}</li>
        <li>{{ __('Your other organizations and your independent work are not affected, and you are not suspended.') }}</li>
    </ul>

    <h3>{{ __('Ending your account') }}</h3>
    <p>{{ __('A counselor account can not be deleted in the app, because clients and organizations depend on its records. Write to support.') }} {{ $tbc(__('what is erased and what is kept when a counselor account ends, and for how long')) }}</p>

    <h2>{{ __('The Feed') }}</h2>
    <ul>
        <li>{{ __('Posts and comments show your name and can be read by everyone signed in. Write only what you are happy to share.') }}</li>
        <li>{{ __('Be kind. Do not harass, threaten, shame or bully anyone, do not post spam, and do not share other people\'s private details.') }}</li>
        <li>{{ __('Anyone can report a post or comment. The MyKaunsel team may set the report aside, remove the writing, or warn you by e-mail. We may remove an account that keeps breaking these rules.') }}</li>
        <li>{{ __('If a post suggests you may be in danger, the app shows phone numbers you can call. Your post is not blocked.') }}</li>
    </ul>

    <h2>{{ __('The chat with the assistant') }}</h2>
    <ul>
        <li>{{ __('You need to agree to the chat first, and you can withdraw your agreement at any time.') }}</li>
        <li>{{ __('The assistant can be wrong. Do not rely on it for decisions about your health or safety.') }}</li>
        <li>{{ __('Please do not type other people\'s private details.') }}</li>
    </ul>

    <h2>{{ __('What you must not do') }}</h2>
    <ul>
        <li>{{ __('Use MyKaunsel to break the law, to hurt people, or to pretend to be someone else.') }}</li>
        <li>{{ __('Try to get into other people\'s accounts or to break or overload the service.') }}</li>
        <li>{{ __('Use a reference code or an invitation that was not meant for you.') }}</li>
    </ul>

    <h2>{{ __('If something goes wrong') }}</h2>
    <p>{{ __('We work to keep MyKaunsel available, but we can not promise it will never be interrupted. We may change or stop parts of it. We may suspend an account that breaks these terms, and we will say why when we can.') }} {{ $tbc(__('limits of liability and the law that applies, to be written with a lawyer')) }}</p>

    <h2>{{ __('Changes') }}</h2>
    <p>{{ __('If we change these terms in a way that matters, we will tell you in the app or by e-mail before the change starts to apply. If you keep using MyKaunsel after that, you accept the new terms.') }}</p>

    <h2>{{ __('Questions') }}</h2>
    <p>{{ __('See the Help page, or write to support.') }}</p>
</x-legal-page>
