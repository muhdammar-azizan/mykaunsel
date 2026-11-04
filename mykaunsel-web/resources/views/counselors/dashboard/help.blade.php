@php
    $h2 = 'text-[20px] font-bold tracking-tight text-brand-ink';
    $p = 'mt-2 text-[14px] leading-relaxed text-brand-ink/80';
    $ul = 'mt-2 list-disc space-y-1.5 pl-5 text-[14px] leading-relaxed text-brand-ink/80';
    $card = 'rounded-xl border border-[#E4E2DE] bg-white p-6 scroll-mt-6';
    $sections = [
        'times' => __('Opening times'),
        'weekly' => __('Weekly hours'),
        'blocks' => __('Blocked times'),
        'buffer' => __('The buffer and hidden times'),
        'bookings' => __('Bookings, the meeting link and marking a session'),
        'cancel' => __('Cancelling a session'),
        'requests' => __('Time requests'),
        'organizations' => __('Organizations'),
        'pa' => __('Your practising certificate (PA)'),
        'clients' => __('What clients see about you'),
        'notices' => __('Notifications and e-mails'),
        'account' => __('Your account'),
        'notes' => __('Session notes'),
        'contact' => __('If you need help'),
    ];
@endphp

<x-portal-layout title="Help">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ __('Help') }}</h1>
    <p class="mt-1 text-[14px] text-brand-ink/65">{{ __('How this portal works, in plain words. All times are Malaysia time.') }}</p>

    <nav aria-label="{{ __('Help topics') }}" class="mt-4 rounded-xl border border-[#E4E2DE] bg-white p-5">
        <p class="text-[13px] font-semibold uppercase tracking-wide text-brand-ink/55">{{ __('On this page') }}</p>
        <ul class="mt-2 grid gap-x-6 gap-y-1.5 text-[14px] font-semibold sm:grid-cols-2">
            @foreach ($sections as $id => $title)
                <li><a href="#{{ $id }}" class="text-brand-blue hover:underline">{{ $title }}</a></li>
            @endforeach
        </ul>
    </nav>

    <div class="mt-5 space-y-5">
        <section id="times" class="{{ $card }}" aria-labelledby="h-times">
            <h2 id="h-times" class="{{ $h2 }}">{{ $sections['times'] }}</h2>
            <p class="{{ $p }}">{{ __('Open a time on the Schedule page: a date, a start time on a 5-minute mark and one of the allowed lengths. Choose where you work: one of your organizations, or your independent practice. A time belongs to one of them. An in-person time needs one of that organization\'s places; an online time keeps no place.') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('Clients can book a time no sooner than :hours hours from now, and up to :days days ahead. You can open times up to :planner days ahead.', ['hours' => $n['lead_hours'], 'days' => $n['client_horizon_days'], 'planner' => $n['planner_horizon_days']]) }}</li>
                <li>{{ __('An in-person time for your independent practice needs one of your own places: add it first on your Profile, under "My places". Clients see only its name and city until they book; the full address goes to them after they book. For an organization, use that organization\'s places.') }}</li>
                <li>{{ __('A time that overlaps another open time, a blocked time or a booked session (with your buffer) is refused, and you are told why.') }}</li>
                <li>{{ __('An organization with no plan, or a day after its plan ends, can not have times opened for it.') }}</li>
                <li>{{ __('You can remove a time that was never booked. A time that has ever had a booking stays, because its history points to it.') }}</li>
            </ul>
        </section>

        <section id="weekly" class="{{ $card }}" aria-labelledby="h-weekly">
            <h2 id="h-weekly" class="{{ $h2 }}">{{ $sections['weekly'] }}</h2>
            <p class="{{ $p }}">{{ __('A weekly line says, for example, "every Monday 09:00 to 12:00, one-hour times, online, for this organization". It is only a pattern: nothing can be booked until you press "Open the next weeks".') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('"Open the next weeks" opens the times of every line for 1 to :weeks weeks, today included. Press it again as the weeks pass: nothing opens twice.', ['weeks' => $n['max_weeks']]) }}</li>
                <li>{{ __('One press may open at most :max times. If it would open more, the whole request is refused and nothing is opened: choose fewer weeks or shorten your hours.', ['max' => $n['max_fill']]) }}</li>
                <li>{{ __('A time that does not fit (already open, in the past, clashing with a booking or a block) is skipped and counted, never an error.') }}</li>
                <li>{{ __('Lines of the same weekday may not overlap. Removing a line does not remove the times it already opened.') }}</li>
                <li>{{ __('Your weekly hours are also what a client\'s time request must fall inside.') }}</li>
            </ul>
        </section>

        <section id="blocks" class="{{ $card }}" aria-labelledby="h-blocks">
            <h2 id="h-blocks" class="{{ $h2 }}">{{ $sections['blocks'] }}</h2>
            <p class="{{ $p }}">{{ __('Use a blocked time for leave, a holiday or a private appointment: whole days, or a stretch of the day, for one day or up to :days days at once.', ['days' => $n['max_block_days']]) }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('A time open for booking inside a block can not be booked. It comes back when you remove the block.') }}</li>
                <li>{{ __('You can not block over a session that is already booked. Cancel that session first.') }}</li>
                <li>{{ __('The note on a block is only for you. Clients only ever see the time as not available.') }}</li>
            </ul>
        </section>

        <section id="buffer" class="{{ $card }}" aria-labelledby="h-buffer">
            <h2 id="h-buffer" class="{{ $h2 }}">{{ $sections['buffer'] }}</h2>
            <p class="{{ $p }}">{{ __('Your buffer (set on your Profile: :options minutes) is kept free before and after every booked session and blocked time, in every organization, because you are one person.', ['options' => implode(', ', $n['buffers'])]) }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('An open time that clashes with a block, or that is too close to a booked session, is left out for clients. They never see it, and they never see it as "booked".') }}</li>
                <li>{{ __('You see these in the calendar on your Schedule page with "Hidden from clients" and the reason written on them: blocked time, or too close to a session.') }}</li>
                <li>{{ __('The calendar shows your week (or one day), Monday to Sunday: open times, blocked times and booked sessions, with your weekly hours as a light background. Click an item to see its details and what you can do with it. A booked session links to its page; the client\'s name is not shown there.') }}</li>
            </ul>
        </section>

        <section id="bookings" class="{{ $card }}" aria-labelledby="h-bookings">
            <h2 id="h-bookings" class="{{ $h2 }}">{{ $sections['bookings'] }}</h2>
            <p class="{{ $p }}">{{ __('Bookings shows three lists: Needs marking (the session has ended), Upcoming and Past. Clients appear by display name only: no e-mail and no phone number.') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('For an online session, paste a meeting link (https only) and choose the app. The client sees the link :minutes minutes before the session starts. Until then they see that it opens later; if you have not added one by then, they see that it has not been added yet.', ['minutes' => $n['link_minutes']]) }}</li>
                <li>{{ __('When a session has ended, mark it completed or no-show. Nothing is marked for you, and once marked it can not be changed. Reviews and the organization\'s reports use what you mark.') }}</li>
                <li>{{ __('You can filter the Past list by status, dates and (if you work in several places) organization.') }}</li>
            </ul>
        </section>

        <section id="cancel" class="{{ $card }}" aria-labelledby="h-cancel">
            <h2 id="h-cancel" class="{{ $h2 }}">{{ $sections['cancel'] }}</h2>
            <p class="{{ $p }}">{{ __('You can cancel one of your sessions until it starts, at any hour: there is no deadline for you. A short reason is required.') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('The client is told by e-mail and in the app, with your reason. Keep it short and kind, and do not write private details about yourself or the client.') }}</li>
                <li>{{ __('The time opens for booking again. Tick "Do not open this time again" if it should stay closed: it becomes a blocked time on your Schedule, and you remove the block to open it later.') }}</li>
                <li>{{ __('Clients can cancel their own session until :hours hours before it (an organization may set another time), and move it up to :moves times. You are told by e-mail and in the portal.', ['hours' => $n['cancel_hours'], 'moves' => $n['max_reschedules']]) }}</li>
                <li>{{ __('There is no way for you to move a booked session to another time: cancel it with a reason and the client books again.') }}</li>
            </ul>
        </section>

        <section id="requests" class="{{ $card }}" aria-labelledby="h-requests">
            <h2 id="h-requests" class="{{ $h2 }}">{{ $sections['requests'] }}</h2>
            <p class="{{ $p }}">{{ __('If you take time requests (Profile), a client can ask for a time inside your weekly hours. Nothing is booked until you answer.') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('A request waits up to :hours hours, or until the time itself if that comes first. If nobody answers, it closes by itself and the client is told.', ['hours' => $n['request_hours']]) }}</li>
                <li>{{ __('Approve books the session. It is refused, and the request stays waiting, if the time no longer fits: it clashes with something, it is outside your weekly hours, or you or the organization are no longer active.') }}</li>
                <li>{{ __('Decline, with a reason if you like: the client sees it.') }}</li>
                <li>{{ __('Each client can have up to :max requests waiting at once. The Answered tab keeps the last :days days.', ['max' => $n['pending_requests'], 'days' => $n['answered_days']]) }}</li>
            </ul>
        </section>

        <section id="organizations" class="{{ $card }}" aria-labelledby="h-organizations">
            <h2 id="h-organizations" class="{{ $h2 }}">{{ $sections['organizations'] }}</h2>
            <p class="{{ $p }}">{{ __('You can work for several organizations and as an independent counselor at the same time. An organization invites you by e-mail; answer on your Dashboard. MyKaunsel has already checked your credentials, so an organization never does.') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('You can leave an organization on the Organizations page, with a reason. Its later sessions with you are cancelled and the clients told, its waiting requests are closed, and the times and weekly hours you opened for it are removed. Your other work is not touched.') }}</li>
                <li>{{ __('An organization can also end your assignment. You are told by e-mail, with the reason it gave.') }}</li>
                <li>{{ __('Your independent practice is managed by MyKaunsel. To end it, contact us.') }}</li>
            </ul>
        </section>

        <section id="pa" class="{{ $card }}" aria-labelledby="h-pa">
            <h2 id="h-pa" class="{{ $h2 }}">{{ $sections['pa'] }}</h2>
            <p class="{{ $p }}">{{ __('Your PA is valid to the end of the last day on it, Malaysia time. Clients can only find and book you while you are approved and your PA is valid.') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('We remind you by e-mail and in the portal :days days before it ends.', ['days' => implode(', ', $n['pa_reminders'])]) }}</li>
                <li>{{ __('When it has expired, your profile is suspended automatically: you are not listed, you can not open new times, and waiting requests are closed. Sessions already booked are kept, and you can still cancel, mark and look after them.') }}</li>
                <li>{{ __('Once you have renewed with LKM, contact MyKaunsel. The team checks the LKM record and lifts the suspension.') }}</li>
                <li>{{ __('If MyKaunsel suspends you for another reason, you are told by e-mail and on your Dashboard, with a link to the details.') }}</li>
            </ul>
        </section>

        <section id="clients" class="{{ $card }}" aria-labelledby="h-clients">
            <h2 id="h-clients" class="{{ $h2 }}">{{ $sections['clients'] }}</h2>
            <p class="{{ $p }}">{{ __('Clients see the name you choose for each place you work (an organization, or independent), your languages and specializations, and the times you open. They do not see your photo (the app shows your initials), your KB or PA number, your e-mail or your documents.') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('Your KB number, PA number, PA date and status come from your application and the LKM check. You can not change them; MyKaunsel does.') }}</li>
                <li>{{ __('Your own specializations are shown on your profile only: they are not a search filter.') }}</li>
            </ul>
        </section>

        <section id="notices" class="{{ $card }}" aria-labelledby="h-notices">
            <h2 id="h-notices" class="{{ $h2 }}">{{ $sections['notices'] }}</h2>
            <p class="{{ $p }}">{{ __('The bell at the top shows what happened that you did not do yourself: a new booking, a client cancelling or moving a session, a new or closed time request, a PA reminder, a suspension. It refreshes about once a minute. Each item opens the right page.') }}</p>
            <ul class="{{ $ul }}">
                <li>{{ __('Notifications show the date and time only: never a client\'s name, note or reason.') }}</li>
                <li>{{ __('Read ones are deleted after :read days and unread ones after :unread days.', ['read' => $n['read_days'], 'unread' => $n['unread_days']]) }}</li>
                <li>{{ __('E-mails about sessions and requests can be switched off in Account, Notifications. Decisions about your account are always sent.') }}</li>
            </ul>
        </section>

        <section id="account" class="{{ $card }}" aria-labelledby="h-account">
            <h2 id="h-account" class="{{ $h2 }}">{{ $sections['account'] }}</h2>
            <ul class="{{ $ul }}">
                <li>{{ __('Changing your password, or choosing "Sign out other devices", needs your current password and signs you out everywhere else, including the app.') }}</li>
                <li>{{ __('Your time zone setting only changes how times are shown on your account pages. Sessions, your calendar, buffers and your PA always follow Malaysia time.') }}</li>
                <li>{{ __('Changing your e-mail address needs a confirmation sent to the new address. You can also turn on two-factor sign-in.') }}</li>
                <li>{{ __('Your account name is the one MyKaunsel compares with your LKM record. You can change it, but if it then no longer matches the record, our team is told.') }}</li>
            </ul>
        </section>

        <section id="notes" class="{{ $card }}" aria-labelledby="h-notes">
            <h2 id="h-notes" class="{{ $h2 }}">{{ $sections['notes'] }}</h2>
            <p class="{{ $p }}">{{ __('Your note about a session is private: only you can read it, and MyKaunsel staff and organizations can not. You can write one once the session has started (not for a cancelled session). Notes are kept for :years years and then erased.', ['years' => $n['note_years']]) }}</p>
        </section>

        <section id="contact" class="{{ $card }}" aria-labelledby="h-contact">
            <h2 id="h-contact" class="{{ $h2 }}">{{ $sections['contact'] }}</h2>
            @if ($support)
                <p class="{{ $p }}">{{ __('Write to MyKaunsel at') }} <a href="mailto:{{ $support }}" class="font-semibold text-brand-blue hover:underline">{{ $support }}</a>. {{ __('Please do not put a client\'s name or private details in an e-mail.') }}</p>
            @else
                <p class="{{ $p }}"><mark class="rounded bg-yellow-100 px-1">{{ __('To be confirmed') }}: {{ __('the support e-mail address') }}</mark></p>
            @endif

            <p class="{{ $p }}">{{ __('If you or someone you work with may be in danger, these lines are free to call:') }}</p>
            <ul class="{{ $ul }}">
                @foreach ($helplines as $line)
                    <li><strong>{{ $line['name'] }}</strong>: <a href="tel:{{ $line['number'] }}" class="font-semibold text-brand-blue hover:underline">{{ $line['number'] }}</a></li>
                @endforeach
            </ul>

            <p class="{{ $p }}">{{ __('This page is about your work as a counselor. The general Help for everyone who uses MyKaunsel (also in the footer) is here:') }} <a href="{{ route('help') }}" class="font-semibold text-brand-blue hover:underline">{{ __('General Help') }}</a></p>

            <p class="{{ $p }}">
                <a href="{{ route('terms') }}" class="font-semibold text-brand-blue hover:underline">{{ __('Terms of Use') }}</a> ·
                <a href="{{ route('privacy') }}" class="font-semibold text-brand-blue hover:underline">{{ __('Privacy Policy') }}</a>
            </p>
        </section>
    </div>
</x-portal-layout>
