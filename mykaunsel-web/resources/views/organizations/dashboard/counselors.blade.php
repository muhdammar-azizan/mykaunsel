@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white p-6';
    $input = 'mt-1 block w-full min-h-[44px] rounded-md border border-brand-ink/20 px-3 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20';
    $primary = 'inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2';
    $outline = 'inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
    $pill = ['approved' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-brand-yellow/25 text-[#6B4C0C]', 'rejected' => 'bg-brand-red/10 text-brand-red', 'suspended' => 'bg-brand-red/10 text-brand-red'];
@endphp

<x-portal-layout title="Counselors">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Counselors</h1>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">The counselors who offer sessions for {{ $organization->name }}. MyKaunsel checks every counselor (KB number, practising certificate and LKM record) before they can be booked: you choose who works for you, MyKaunsel verifies them.</p>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <section class="{{ $card }} mt-5" aria-labelledby="list-title">
        <h2 id="list-title" class="text-[20px] font-bold tracking-tight">Your counselors ({{ $counselors->count() }})</h2>

        <ul class="mt-3 divide-y divide-[#EEEDEA] border-t border-[#EEEDEA]">
            @forelse ($counselors as $m)
                @php
                    $profile = $m->user->counselorProfile;
                    $status = $profile?->verification_status?->value ?? 'pending';
                    $left = $profile?->pa_valid_until ? \App\Support\PaValidity::daysLeft($profile->pa_valid_until) : null;
                @endphp
                <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="min-w-0">
                        <p class="text-[15px] font-semibold">{{ $m->user->name }}</p>
                        <p class="text-[13px] text-brand-ink/65">{{ $m->user->email }} · works with you since {{ $m->joined_at?->setTimezone($timezone)->format('j M Y') }}</p>
                        @if ($left !== null && $left < 0)<p class="mt-0.5 text-[12.5px] font-bold text-brand-red">Practising certificate expired</p>
                        @elseif ($left !== null && $left <= 30)<p class="mt-0.5 text-[12.5px] font-bold text-[#6B4C0C]">Practising certificate ends in {{ $left }} {{ $left === 1 ? 'day' : 'days' }}</p>@endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $pill[$status] ?? 'bg-brand-ink/10 text-brand-ink/65' }}">{{ $status === 'approved' ? 'Verified' : ucfirst($status) }}</span>
                        <form method="POST" action="{{ route('org.counselors.remove', $m->id) }}" data-confirm-title="Remove {{ $m->user->name }}?" data-confirm="Their later sessions with your organization are cancelled and the clients are told. It can not be undone." data-confirm-button="Remove counselor" data-confirm-cancel="Keep" data-confirm-input="reason" data-confirm-input-label="Reason (only {{ $m->user->name }} will see this, never the clients)" data-confirm-input-placeholder="Tell them why their assignment is ending">
                            @csrf
                            <button class="inline-flex min-h-[40px] items-center rounded-md border border-brand-red/50 px-4 text-[14px] font-semibold text-brand-red transition hover:bg-brand-red/[.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-red">Remove</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="py-6 text-[14px] text-brand-ink/65">No counselors yet. Invite one below.</li>
            @endforelse
        </ul>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="{{ $card }}" aria-labelledby="invite-title">
            <h2 id="invite-title" class="text-[20px] font-bold tracking-tight">Invite a counselor</h2>
            <p class="mt-1 text-[14px] text-brand-ink/65">Write the e-mail address of a counselor who has a MyKaunsel counselor account approved by MyKaunsel. They sign in to the website with that address and accept on their Dashboard.</p>
            <form method="POST" action="{{ route('org.counselors.invite') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-[220px] flex-1">
                    <label for="email" class="block text-[13px] font-semibold text-brand-ink/70">E-mail address</label>
                    <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="{{ $input }}">
                </div>
                <button class="{{ $primary }}">Send invitation</button>
            </form>

            <ul class="mt-5 divide-y divide-[#EEEDEA] border-t border-[#EEEDEA]">
                @forelse ($invitations as $i)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-[14px]">
                        <span class="min-w-0 truncate">{{ $i->email }}</span>
                        <span class="flex items-center gap-3">
                            <span class="text-[13px] text-brand-ink/65">{{ $i->state() }}@if ($i->isOpen()) · until {{ $i->expires_at->setTimezone($timezone)->format('j M Y') }}@endif</span>
                            @if ($i->isOpen())
                                <form method="POST" action="{{ route('org.counselor-invitations.revoke', $i->id) }}">@csrf<button class="text-[13px] font-semibold text-brand-blue hover:underline">Cancel</button></form>
                            @endif
                        </span>
                    </li>
                @empty
                    <li><x-empty-state-invitation title="No invitations yet." text="Invitations you send will show up here." /></li>
                @endforelse
            </ul>
        </section>

        <form method="POST" action="{{ route('org.counselors.settings') }}" class="{{ $card }} space-y-5 self-start" aria-labelledby="settings-title">
            @csrf
            @method('PUT')
            <h2 id="settings-title" class="text-[20px] font-bold tracking-tight">Who may be booked</h2>

            <fieldset>
                <legend class="text-[13px] font-semibold text-brand-ink/70">Who can book your counselors' times</legend>
                @foreach (['closed' => ['Only our members', 'Students, staff or employees of your organization.'], 'open' => ['Anyone', 'Any MyKaunsel user, for example a clinic that also takes the public.']] as $value => [$label, $hint])
                    <label class="mt-2 flex items-start gap-3">
                        <input type="radio" name="access_model" value="{{ $value }}" @checked(old('access_model', $organization->access_model->value) === $value) class="mt-1 h-4 w-4 border-brand-ink/30 text-brand-blue">
                        <span class="text-[14px]"><span class="font-semibold">{{ $label }}</span><span class="block text-[13px] text-brand-ink/65">{{ $hint }}</span></span>
                    </label>
                @endforeach
            </fieldset>

            <label class="flex items-start gap-3 border-t border-[#EEEDEA] pt-5">
                <input type="checkbox" name="allow_counselor_freelance" value="1" @checked(old('allow_counselor_freelance', $organization->allow_counselor_freelance)) class="mt-1 h-5 w-5 rounded border-brand-ink/30 text-brand-blue">
                <span class="text-[14px]"><span class="font-semibold">Our counselors may also take independent clients on MyKaunsel</span><span class="block text-[13px] text-brand-ink/65">Switched off, a counselor who already works independently on MyKaunsel can not accept your invitation until that ends.</span></span>
            </label>

            <button class="{{ $primary }}">Save settings</button>
        </form>
    </div>
</x-portal-layout>
