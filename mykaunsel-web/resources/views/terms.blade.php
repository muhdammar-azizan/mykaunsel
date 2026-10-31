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
        <li>{{ __('You can belong to only one organization at a time. To join another one, leave the first.') }}</li>
        <li>{{ __('You can delete your account in the app at any time. What happens then is in the Privacy Policy.') }}</li>
    </ul>

    <h2>{{ __('Counselors and sessions') }}</h2>
    <ul>
        <li>{{ __('Counselors on MyKaunsel are independent professionals. We show the registration details they give us and check them against the records we can reach, but a session is between you and the counselor.') }} {{ $tbc(__('the wording of what MyKaunsel is and is not responsible for')) }}</li>
        <li>{{ __('You can cancel or move a booking until the deadline shown when you book. The deadline is set by the organization the counselor works for. A session can be moved a limited number of times.') }}</li>
        <li>{{ __('If a session can not go ahead, for example because the counselor becomes unavailable, we tell you by e-mail.') }}</li>
        <li>{{ __('Sessions with an organization\'s counselors are for members of that organization. If you leave the organization, your later sessions with its counselors are cancelled, except those in the next few days, as the app tells you before you leave.') }}</li>
        <li>{{ __('Please come on time and be respectful.') }}</li>
    </ul>

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
