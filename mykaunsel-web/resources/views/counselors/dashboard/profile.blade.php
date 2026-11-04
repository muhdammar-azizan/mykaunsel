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

            {{-- What was checked when they applied (applicants only) and the documents on file --}}
            @if ($p['lkm'])
                <p class="mt-4 rounded-lg px-4 py-2.5 text-[13.5px] font-semibold {{ ['ok' => 'bg-emerald-50 text-emerald-700', 'warn' => 'bg-[#FDF4E3] text-[#6B4C0C]', 'bad' => 'bg-brand-red/[.06] text-brand-red'][$p['lkm']['tone']] }}" data-lkm-result="{{ $p['lkm']['tone'] }}">{{ $p['lkm']['text'] }}</p>
            @endif

            @if (collect($p['documents'])->contains(fn ($d) => $d['status'] !== 'missing'))
                <h3 class="mt-5 text-[15px] font-bold tracking-tight">Your documents</h3>
                <ul class="mt-2 divide-y divide-[#EEEDEA] text-[14px]" aria-label="Documents on file">
                    @foreach ($p['documents'] as $document)
                        <li class="py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-semibold">{{ $document['label'] }}</span>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ ['ok' => 'bg-emerald-50 text-emerald-700', 'needs_replacement' => 'bg-brand-red/10 text-brand-red', 'unreviewed' => 'bg-brand-ink/10 text-brand-ink/65', 'missing' => 'bg-brand-ink/10 text-brand-ink/65'][$document['status']] ?? 'bg-brand-ink/10 text-brand-ink/65' }}">{{ $document['status_label'] }}</span>
                            </div>
                            @if ($document['file_name'])<p class="mt-0.5 text-[12.5px] text-brand-ink/55">{{ $document['file_name'] }}@if ($document['uploaded_on']) · uploaded {{ $document['uploaded_on'] }}@endif</p>@endif
                            @if ($document['note'])<p class="mt-1 text-[12.5px] text-brand-red">Note from our team: {{ $document['note'] }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
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

                {{-- Their own: up to a few short words, shown on the profile only (not a search filter). --}}
                <div class="mt-4"
                     x-data="{
                        items: @js(array_values(old('custom_specializations', $p['custom_specializations']))), draft: '', error: '', max: {{ $p['custom_specialization_max'] }},
                        add() {
                            const v = this.draft.replace(/\s+/g, ' ').trim();
                            this.error = '';
                            if (v === '') return;
                            if (this.items.length >= this.max) { this.error = 'You can add up to ' + this.max + ' of your own.'; return; }
                            if (v.length > {{ \App\Services\CounselorProfileSettings::CUSTOM_MAX_CHARS }}) { this.error = 'Keep it under {{ \App\Services\CounselorProfileSettings::CUSTOM_MAX_CHARS + 1 }} characters.'; return; }
                            if (v.split(' ').length > {{ \App\Services\CounselorProfileSettings::CUSTOM_MAX_WORDS }}) { this.error = 'Use at most {{ \App\Services\CounselorProfileSettings::CUSTOM_MAX_WORDS }} words.'; return; }
                            if (!/^\p{L}[\p{L} &'\/-]*$/u.test(v)) { this.error = 'Use letters only (you can also use & - / and spaces).'; return; }
                            if (this.items.some(i => i.toLowerCase() === v.toLowerCase()) || @js(array_map('mb_strtolower', $p['specialization_options'])).includes(v.toLowerCase())) { this.error = 'That one is already there.'; return; }
                            this.items.push(v.charAt(0).toUpperCase() + v.slice(1));
                            this.draft = '';
                        },
                     }">
                    <label for="custom-spec" class="block text-[13px] font-semibold text-brand-ink/70">Add your own <span class="font-normal text-brand-ink/55">(up to {{ $p['custom_specialization_max'] }}, a few words each)</span></label>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <input id="custom-spec" type="text" x-model="draft" @keydown.enter.prevent="add()" maxlength="{{ \App\Services\CounselorProfileSettings::CUSTOM_MAX_CHARS }}" :disabled="items.length >= max" autocomplete="off" placeholder="For example: Parenting"
                               class="min-h-[40px] w-full max-w-[260px] rounded-md border border-brand-ink/20 px-3 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-1 focus:ring-brand-blue disabled:cursor-not-allowed disabled:bg-brand-ink/5">
                        <button type="button" @click="add()" :disabled="items.length >= max" class="inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/25 px-4 text-[14px] font-semibold transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue disabled:cursor-not-allowed disabled:opacity-50">+ Add</button>
                    </div>
                    <p x-show="error" x-cloak role="alert" class="mt-1.5 text-[13px] font-semibold text-brand-red" x-text="error"></p>
                    @error('custom_specializations')<p class="mt-1.5 text-[13px] font-semibold text-brand-red">{{ $message }}</p>@enderror
                    @error('custom_specializations.*')<p class="mt-1.5 text-[13px] font-semibold text-brand-red">{{ $message }}</p>@enderror

                    <ul class="mt-2 flex flex-wrap gap-2" x-show="items.length" x-cloak>
                        <template x-for="(item, i) in items" :key="item">
                            <li class="inline-flex items-center gap-1.5 rounded-full bg-brand-blue/10 py-1 pl-3 pr-1.5 text-[13.5px] font-medium text-brand-blue">
                                <span x-text="item"></span>
                                <input type="hidden" name="custom_specializations[]" :value="item">
                                <button type="button" @click="items.splice(i, 1)" :aria-label="'Remove ' + item" class="grid h-5 w-5 place-items-center rounded-full hover:bg-brand-blue/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-2 text-[12.5px] text-brand-ink/55">Your own words show on your profile. They are not used to filter or search; MyKaunsel can add a popular one to the list.</p>
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

    @if ($independent)
        <section id="my-places" class="{{ $card }} mt-5 scroll-mt-6" aria-labelledby="places-title">
            <h2 id="places-title" class="text-[20px] font-bold tracking-tight">My places</h2>
            <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/70">Where you see clients in person for your independent practice. When you open an in-person time for your independent practice, you pick one of these. Clients see only the name and the city; the full address goes to a client after they book. Do not use your home address as the name of the place.</p>

            <ul class="mt-4 space-y-3">
                @forelse ($places as $place)
                    <li class="rounded-xl border border-[#E4E2DE] p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $place['name'] }} <span class="font-normal text-brand-ink/65">· {{ $place['city'] }}</span></p>
                                <p class="text-[13px] text-brand-ink/65">{{ $place['address'] }}, {{ $place['postcode'] }} {{ $place['city'] }}, {{ $place['state'] }}</p>
                                @if ($place['has_pin'])
                                    <p class="mt-1 text-[12.5px] text-emerald-800">Pin set on the map ·
                                        <a href="https://www.openstreetmap.org/?mlat={{ $place['latitude'] }}&amp;mlon={{ $place['longitude'] }}#map=18/{{ $place['latitude'] }}/{{ $place['longitude'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand-blue hover:underline">View on map</a></p>
                                @else
                                    <p class="mt-1 text-[12.5px] font-semibold text-[#6B4C0C]">No pin on the map yet. Change this place and set the pin.</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if (! $place['change_blocked'])
                                    <a href="{{ route('counselor.places.edit', $place['id']) }}" class="inline-flex min-h-[44px] items-center rounded-md border border-brand-ink/25 px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">Change</a>
                                @endif
                                @if (! $place['remove_blocked'])
                                    <form method="POST" action="{{ route('counselor.places.destroy', $place['id']) }}"
                                          data-confirm-title="Remove {{ $place['name'] }}?" data-confirm="It will not be offered for new times. Past sessions keep it in their history." data-confirm-button="Remove place" data-confirm-cancel="Keep">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex min-h-[44px] items-center rounded-md border border-brand-red/50 px-4 text-[14px] font-semibold text-brand-red transition hover:bg-brand-red/[.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-red">Remove</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($place['change_blocked'])
                            <p class="mt-2 text-[12.5px] text-brand-ink/60">{{ $place['change_blocked'] }}</p>
                        @endif
                        @if ($place['remove_blocked'])
                            <p class="mt-1 text-[12.5px] text-brand-ink/60">{{ $place['remove_blocked'] }}</p>
                        @endif
                    </li>
                @empty
                    <li class="rounded-xl border border-[#E4E2DE] p-5 text-[14px] text-brand-ink/65">You have no places yet. Add one to open in-person times for your independent practice.</li>
                @endforelse
            </ul>

            @if (count($places) < $maxPlaces)
                <a href="{{ route('counselor.places.create') }}" class="mt-4 inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2">Add a place</a>
            @else
                <p class="mt-4 text-[13px] text-brand-ink/65">You have the most places allowed ({{ $maxPlaces }}). Remove one you do not use to add another.</p>
            @endif
        </section>
    @endif
</x-portal-layout>
