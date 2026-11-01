@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white p-6';
    $input = 'mt-1 block w-full min-h-[44px] rounded-md border border-brand-ink/20 px-3 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20';
    $status = $p['status'];
    $pill = ['approved' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-brand-yellow/25 text-[#6B4C0C]', 'rejected' => 'bg-brand-red/10 text-brand-red', 'suspended' => 'bg-brand-red/10 text-brand-red'][$status->value] ?? 'bg-brand-ink/10 text-brand-ink/65';
    $left = $p['pa_days_left'];
@endphp

<x-portal-layout title="Profile">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Profile</h1>
    <p class="mt-1 text-[14px] text-brand-ink/65">How clients see you and how they can book you. Your verification details come from your application and the LKM check: only the MyKaunsel team can change them.</p>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <section class="{{ $card }} self-start" aria-labelledby="verification-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="verification-title" class="text-[20px] font-bold tracking-tight">Verification</h2>
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $pill }}">{{ ucfirst($status->value) }}</span>
            </div>

            @if ($status->value !== 'approved')
                <p class="mt-3 rounded-lg bg-brand-red/[.06] px-4 py-2.5 text-[13.5px] font-semibold text-brand-red">Your profile is {{ $status->value }}, so people can not book you.</p>
            @endif

            <dl class="mt-3 divide-y divide-[#EEEDEA] text-[14px]">
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Kind of counselor</dt><dd class="text-right font-semibold">{{ $p['independent'] ? 'Independent, verified by MyKaunsel' : 'Assigned by an organization' }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">KB number</dt><dd class="font-semibold">{{ $p['kb_number'] }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Practising certificate (PA)</dt><dd class="font-semibold">{{ $p['pa_number'] ?: 'Not recorded' }}</dd></div>
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-brand-ink/65">PA valid until</dt>
                    <dd class="text-right font-semibold">
                        {{ $p['pa_valid_until'] ?: 'Not recorded' }}
                        @if ($left !== null && $left < 0)<span class="mt-1 block text-[12.5px] font-bold text-brand-red">Expired</span>
                        @elseif ($left !== null && $left <= 30)<span class="mt-1 block text-[12.5px] font-bold text-[#6B4C0C]">{{ $left }} {{ $left === 1 ? 'day' : 'days' }} left</span>@endif
                    </dd>
                </div>
            </dl>
            <p class="mt-3 text-[12.5px] text-brand-ink/55">Renew your PA with LKM before it runs out. MyKaunsel checks it every day and reminds you.</p>
        </section>

        <form method="POST" action="{{ route('counselor.profile.update') }}" class="{{ $card }} space-y-6" aria-labelledby="edit-title">
            @csrf
            @method('PUT')
            <h2 id="edit-title" class="text-[20px] font-bold tracking-tight">How clients see and book you</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="n-org" class="block text-[13px] font-semibold text-brand-ink/70">Name shown for your organization</label>
                    <input id="n-org" name="display_name_org" type="text" maxlength="100" value="{{ old('display_name_org', $p['display_name_org']) }}" placeholder="{{ $p['account_name'] }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="n-ind" class="block text-[13px] font-semibold text-brand-ink/70">Name shown for independent work</label>
                    <input id="n-ind" name="display_name_independent" type="text" maxlength="100" value="{{ old('display_name_independent', $p['display_name_independent']) }}" placeholder="{{ $p['account_name'] }}" class="{{ $input }}">
                </div>
                <p class="text-[12.5px] text-brand-ink/55 sm:col-span-2">Leave a name empty to use your account name ({{ $p['account_name'] }}). Use the name you practise under.</p>
            </div>

            <fieldset>
                <legend class="text-[13px] font-semibold text-brand-ink/70">Languages</legend>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                    @foreach ($p['language_options'] as $option)
                        <label class="inline-flex min-h-[32px] items-center gap-2 text-[14px]"><input type="checkbox" name="languages[]" value="{{ $option }}" @checked(in_array($option, old('languages', $p['languages']), true)) class="h-4 w-4 rounded border-brand-ink/30 text-brand-blue">{{ $option }}</label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-[13px] font-semibold text-brand-ink/70">Specializations</legend>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                    @foreach ($p['specialization_options'] as $option)
                        <label class="inline-flex min-h-[32px] items-center gap-2 text-[14px]"><input type="checkbox" name="specializations[]" value="{{ $option }}" @checked(in_array($option, old('specializations', $p['specializations']), true)) class="h-4 w-4 rounded border-brand-ink/30 text-brand-blue">{{ $option }}</label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-4 border-t border-[#EEEDEA] pt-5 sm:grid-cols-2">
                <div>
                    <label for="provider" class="block text-[13px] font-semibold text-brand-ink/70">Usual meeting app</label>
                    <select id="provider" name="meeting_provider" class="{{ $input }}">
                        <option value="">No preference</option>
                        @foreach ($p['providers'] as $provider)<option value="{{ $provider }}" @selected(old('meeting_provider', $p['meeting_provider']) === $provider)>{{ $provider }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="buffer" class="block text-[13px] font-semibold text-brand-ink/70">Gap between sessions</label>
                    <select id="buffer" name="buffer_minutes" class="{{ $input }}">
                        @foreach ($p['buffer_options'] as $minutes)<option value="{{ $minutes }}" @selected((int) old('buffer_minutes', $p['buffer_minutes']) === $minutes)>{{ $minutes === 0 ? 'No gap' : $minutes.' minutes' }}</option>@endforeach
                    </select>
                </div>
                <label class="flex items-start gap-3 sm:col-span-2">
                    <input type="checkbox" name="accepts_requests" value="1" @checked(old('accepts_requests', $p['accepts_requests']) ? true : false) class="mt-1 h-5 w-5 rounded border-brand-ink/30 text-brand-blue">
                    <span class="text-[14px]"><span class="font-semibold">Accept time requests</span><span class="block text-[13px] text-brand-ink/65">Clients may ask for a time inside your weekly hours. You approve or decline each one.</span></span>
                </label>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-[#EEEDEA] pt-5">
                <p class="text-[12.5px] text-brand-ink/55">Photo and a written introduction are not available yet: they need an approval step first.</p>
                <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-6 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2">Save profile</button>
            </div>
        </form>
    </div>
</x-portal-layout>
