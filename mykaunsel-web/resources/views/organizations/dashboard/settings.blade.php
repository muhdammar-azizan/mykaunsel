@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white p-6';
    $input = 'mt-1 block w-full min-h-[44px] rounded-md border border-brand-ink/20 px-3 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20';
    $join = $organization->join_method ? ucwords(str_replace('_', ' ', $organization->join_method->value)) : 'Not applicable';
@endphp

<x-portal-layout title="Settings">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Settings</h1>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">Details of {{ $organization->name }}. MyKaunsel checked the name, the registration number and the e-mail domains when it approved your organization, so only MyKaunsel changes them: write to support if one is wrong.</p>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="{{ $card }}" aria-labelledby="details-title">
            <h2 id="details-title" class="text-[20px] font-bold tracking-tight">Your organization</h2>
            <dl class="mt-3 divide-y divide-[#EEEDEA] text-[14px]">
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Name</dt><dd class="text-right font-semibold">{{ $organization->name }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Kind</dt><dd class="font-semibold">{{ ucfirst($organization->org_type->value) }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Registration number (SSM)</dt><dd class="font-semibold">{{ $organization->ssm_number ?: 'Not recorded' }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Status</dt><dd class="font-semibold">{{ ucfirst($organization->subscription_status->value) }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">How members join</dt><dd class="font-semibold">{{ $join }}</dd></div>
            </dl>
        </section>

        <section class="{{ $card }}" aria-labelledby="domains-title">
            <h2 id="domains-title" class="text-[20px] font-bold tracking-tight">E-mail domains</h2>
            <p class="mt-1 text-[13.5px] text-brand-ink/65">People who sign up with one of these addresses can join your organization on their own.</p>
            <ul class="mt-3 divide-y divide-[#EEEDEA] border-t border-[#EEEDEA]">
                @forelse ($domains as $domain)
                    <li class="flex items-center justify-between gap-3 py-3 text-[14px]">
                        <span class="font-semibold">{{ $domain->domain }}</span>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $domain->dns_verified ? 'bg-emerald-50 text-emerald-700' : 'bg-brand-yellow/25 text-[#6B4C0C]' }}">{{ $domain->dns_verified ? 'Verified' : 'Not verified' }}</span>
                    </li>
                @empty
                    <li class="py-3 text-[14px] text-brand-ink/65">No e-mail domain. People join with a reference code or an invitation.</li>
                @endforelse
            </ul>
        </section>

        <form method="POST" action="{{ route('org.settings.update') }}" class="{{ $card }} self-start" aria-labelledby="rules-title">
            @csrf
            @method('PUT')
            <h2 id="rules-title" class="text-[20px] font-bold tracking-tight">Cancelling a session</h2>
            <label for="deadline" class="mt-3 block text-[13px] font-semibold text-brand-ink/70">A member may cancel or move a session up to this long before it starts</label>
            <select id="deadline" name="cancellation_deadline_hours" class="{{ $input }}">
                @foreach ($deadline_choices as $hours)<option value="{{ $hours }}" @selected((int) old('cancellation_deadline_hours', $deadline_hours) === $hours)>{{ $hours }} {{ $hours === 1 ? 'hour' : 'hours' }} before</option>@endforeach
            </select>
            <p class="mt-2 text-[12.5px] text-brand-ink/55">It applies to every session with your counselors, including ones already booked. A longer time protects your counselors; a shorter one is kinder to members.</p>
            <button class="mt-4 inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2">Save</button>
        </form>

        <section class="{{ $card }} self-start" aria-labelledby="admins-title">
            <h2 id="admins-title" class="text-[20px] font-bold tracking-tight">Administrators</h2>
            <ul class="mt-3 divide-y divide-[#EEEDEA] border-t border-[#EEEDEA]">
                @foreach ($admins as $m)
                    <li class="py-3 text-[14px]"><span class="font-semibold">{{ $m->user->name }}</span> <span class="text-brand-ink/65">· {{ $m->user->email }}</span></li>
                @endforeach
            </ul>
            <p class="mt-3 text-[12.5px] text-brand-ink/55">To add or remove an administrator, write to MyKaunsel support. Who may be booked, and independent work by your counselors, are on the <a href="{{ route('org.counselors') }}" class="font-semibold text-brand-blue hover:underline">Counselors</a> page.</p>
        </section>
    </div>
</x-portal-layout>
