@php
    $support = config('api.support_email');
@endphp

<x-legal-page :title="__('Help')">
    <h2>{{ __('If you need to talk to someone right now') }}</h2>
    <p>{{ __('These lines are free to call. Please call if you or someone you know may be in danger.') }}</p>
    <ul>
        @foreach (config('chat.helplines') as $line)
            <li><strong>{{ $line['name'] }}</strong>: <a href="tel:{{ $line['number'] }}">{{ $line['number'] }}</a></li>
        @endforeach
    </ul>

    <h2>{{ __('Contact support') }}</h2>
    @if ($support)
        <p>{{ __('Write to us at') }} <a href="mailto:{{ $support }}">{{ $support }}</a>. {{ __('Please do not write private details about your health in an e-mail.') }}</p>
    @else
        <p><mark class="rounded bg-yellow-100 px-1">{{ __('To be confirmed') }}: {{ __('the support e-mail address') }}</mark></p>
    @endif

    <h2>{{ __('Common questions') }}</h2>

    <h3 class="font-semibold text-navy">{{ __('How do I book a counselor?') }}</h3>
    <p>{{ __('Open Home, then Find a counselor. Pick a counselor, choose a time, check the details and confirm. If a counselor has no times to pick, you can ask for a time instead and they will answer.') }}</p>

    <h3 class="font-semibold text-navy">{{ __('How do I cancel or move a booking?') }}</h3>
    <p>{{ __('Open Bookings, then the booking. You can cancel or move it until the deadline shown there.') }}</p>

    <h3 class="font-semibold text-navy">{{ __('My organization gave me a code. What do I do?') }}</h3>
    <p>{{ __('Open Account, then Your organization, and enter the code. Your organization then has to approve you. If your organization invited your e-mail address instead, accept the invitation on the same page. To see the counselors of an organization you must be a member of it.') }}</p>

    <h3 class="font-semibold text-navy">{{ __('How do I use the chat?') }}</h3>
    <p>{{ __('Open Chat. The first time, it asks you to agree. You can withdraw your agreement in Account at any time. The chat is an AI companion, not a counselor.') }}</p>

    <h3 class="font-semibold text-navy">{{ __('I forgot my password.') }}</h3>
    <p>{{ __('On the sign-in page, choose Forgot password. We send a link to your e-mail address.') }}</p>

    <h3 class="font-semibold text-navy">{{ __('How do I delete my account?') }}</h3>
    <p>{{ __('Open Account, then Delete account. It lists what is erased and what stays, and asks for your password.') }}</p>

    <h2>{{ __('More') }}</h2>
    <ul>
        <li><a href="{{ route('terms') }}">{{ __('Terms of Use') }}</a></li>
        <li><a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a></li>
    </ul>
</x-legal-page>
