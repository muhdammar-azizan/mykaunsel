@push('styles')
    <style>
        .masuk { animation: masuk .5s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

        .step-panel { animation: masukStep .35s cubic-bezier(.32,.72,0,1) both; }
        .step-panel.keluar { animation: keluarStep .25s ease both; }
        @keyframes masukStep { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @keyframes keluarStep { from { opacity: 1; transform: none; } to { opacity: 0; transform: translateY(-8px); } }

        .dot { width: 26px; height: 4px; border-radius: 999px; background: rgba(14,42,51,.15); transition: background-color .3s ease; }
        .dot.selesai { background: #0F6B7D; }
        .dot.semasa { background: #0E2A33; }

        .org-pilih { position: relative; text-align: left; border-radius: 14px; border: 1px solid rgba(14,42,51,.12); background: #FAF8F5;
            padding: 20px; transition: border-color .2s ease, background-color .2s ease, transform .2s ease, box-shadow .2s ease; cursor: pointer; }
        .org-pilih:hover { border-color: rgba(14,42,51,.25); transform: translateY(-2px); box-shadow: 0 14px 32px -18px rgba(14,42,51,.22); }
        .org-pilih[aria-pressed="true"] { border: 2px solid #4A7DFF; padding: 19px; background: rgba(74,125,255,.04); }
        .org-radio { transition: background-color .2s ease, border-color .2s ease; }
        .org-pilih[aria-pressed="true"] .org-radio { background: #4A7DFF; border-color: #4A7DFF; }
        .org-semak { transform: scale(0); transition: transform .2s cubic-bezier(.34,1.56,.64,1); }
        .org-pilih[aria-pressed="true"] .org-semak { transform: scale(1); }

        .klinik-extra { overflow: hidden; transition: max-height .3s ease, opacity .3s ease, margin-top .3s ease; }
        .klinik-extra[hidden] { display: block !important; max-height: 0; opacity: 0; margin-top: 0; pointer-events: none; }
        .klinik-extra:not([hidden]) { max-height: 1400px; opacity: 1; }

        .ringkasan-baris { opacity: 0; transform: translateY(6px); animation: masuk .4s cubic-bezier(.32,.72,0,1) both; animation-delay: var(--tunda,0s); }

        .domain-baris { animation: masukDomain .25s ease both; }
        @keyframes masukDomain { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: none; } }
        .domain-baris.keluar { animation: keluarDomain .2s ease both; }
        @keyframes keluarDomain { from { opacity: 1; transform: none; } to { opacity: 0; transform: translateY(-8px); } }
        .domain-lapis { transition: opacity .3s ease; }

        @media (prefers-reduced-motion: reduce) {
            .masuk, .step-panel, .ringkasan-baris, .domain-baris { animation: none !important; opacity: 1 !important; transform: none !important; }
            .org-pilih:hover { transform: none; }
        }
    </style>
@endpush

<x-auth-wizard-layout
    title="Register Your Organization — MyKaunsel"
    image-slot="org-signup-visual"
    aside-headline="Bring verified counseling to your institution."
    aside-subtext="Manage counselors, availability, and bookings — all in one place, built for how counseling centers actually work."
    content-max-width="460px"
>
    @php
        $oldOrgType = old('org_type');
        $errorStep = 1;
        if ($errors->any()) {
            $errorStep = 4;
            if ($errors->hasAny(['org_type', 'access_model'])) $errorStep = 1;
            elseif ($errors->hasAny(['org_name', 'org_size', 'ssm_number', 'location.name', 'location.address', 'location.city', 'location.state', 'location.postcode', 'photos', 'opt_location.name'])) $errorStep = 2;
            elseif ($errors->hasAny(['domains', 'no_domain']) || $errors->keys() && collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'domains.'))) $errorStep = 3;
        }
        $isKlinikOld = $oldOrgType === 'clinic';
    @endphp

    <!-- Progress -->
    <div class="mb-8">
        <div id="dotsWrap" class="flex items-center gap-2">
            <span class="dot" data-pos="1"></span>
            <span class="dot" data-pos="2"></span>
            <span class="dot" data-pos="3"></span>
            <span class="dot" data-pos="4"></span>
        </div>
        <p id="stepLabel" class="mt-2.5 text-[12.5px] font-medium uppercase tracking-[0.1em] text-navy/45">{{ __('Step 1 of 4 — Organization type') }}</p>
    </div>

    <a href="{{ route('login') }}" class="mb-5 inline-flex items-center gap-1.5 text-[13px] text-navy/50 hover:text-navy"><span aria-hidden="true">←</span> {{ __('Back to log in') }}</a>

    <form id="orgSignupForm" method="POST" action="{{ route('organizations.signup.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        <input type="hidden" name="org_type" id="orgTypeInput" value="{{ $oldOrgType }}">
        <input type="hidden" name="access_model" id="accessModelInput" value="{{ old('access_model') }}">

        <!-- STEP 1 -->
        <div id="step1" class="step-panel" @if ($errorStep !== 1) hidden @endif>
            <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('What type of organization are you?') }}</h1>
            <p class="mt-2 text-[13.5px] leading-relaxed text-navy/60">{{ __('This helps us set up the right access rules for your team.') }}</p>

            <div class="mt-6 flex flex-col gap-3">
                <button type="button" class="org-pilih flex items-start gap-4" data-tipe="university" data-akses="closed" aria-pressed="{{ $oldOrgType === 'university' ? 'true' : 'false' }}">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" style="background:rgba(15,107,125,.10);color:#0F6B7D">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 20V8L12 2 2 8v12"/><path d="M6 20v-8h12v8"/><path d="M12 2v6"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-[15px] font-semibold text-navy">{{ __('University / Higher Education') }}</span>
                        <span class="mt-1 block text-[13px] leading-relaxed text-navy/55">{{ __('Only your students and staff can book your counselors.') }}</span>
                    </span>
                    <span class="org-radio mt-1 grid h-[20px] w-[20px] shrink-0 place-items-center rounded-full border-2 border-navy/20" aria-hidden="true">
                        <svg class="org-semak" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                    </span>
                </button>

                <button type="button" class="org-pilih flex items-start gap-4" data-tipe="corporate" data-akses="closed" aria-pressed="{{ $oldOrgType === 'corporate' ? 'true' : 'false' }}">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" style="background:rgba(217,143,74,.12);color:#D98F4A">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 21v-4h6v4"/><path d="M9 8h.01M9 12h.01M15 8h.01M15 12h.01"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-[15px] font-semibold text-navy">{{ __('Company / Corporate') }}</span>
                        <span class="mt-1 block text-[13px] leading-relaxed text-navy/55">{{ __('Only your employees can book your counselors.') }}</span>
                    </span>
                    <span class="org-radio mt-1 grid h-[20px] w-[20px] shrink-0 place-items-center rounded-full border-2 border-navy/20" aria-hidden="true">
                        <svg class="org-semak" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                    </span>
                </button>

                <button type="button" class="org-pilih flex items-start gap-4" data-tipe="clinic" data-akses="open" aria-pressed="{{ $oldOrgType === 'clinic' ? 'true' : 'false' }}">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" style="background:rgba(74,125,255,.10);color:#4A7DFF">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/><path d="M12 6v4M10 8h4"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-[15px] font-semibold text-navy">{{ __('Clinic / Private Practice') }}</span>
                        <span class="mt-1 block text-[13px] leading-relaxed text-navy/55">{{ __('Open to all MyKaunsel users, not limited to a member list.') }}</span>
                    </span>
                    <span class="org-radio mt-1 grid h-[20px] w-[20px] shrink-0 place-items-center rounded-full border-2 border-navy/20" aria-hidden="true">
                        <svg class="org-semak" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                    </span>
                </button>
            </div>

            <button type="button" id="s1Continue" @if (!$oldOrgType) disabled @endif class="btn-utama mt-6 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Continue') }}</button>
        </div>

        <!-- STEP 2 -->
        <div id="step2" class="step-panel" hidden>
            <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Tell us about your organization') }}</h1>
            <p id="s2Sub" class="mt-2 text-[13.5px] leading-relaxed text-navy/60">{{ __('This information appears to your members.') }}</p>

            <div class="mt-6">
                <label class="block">
                    <span id="orgNameLabel" class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Organization Name') }}</span>
                    <input type="text" name="org_name" id="orgName" value="{{ old('org_name') }}" placeholder="Universiti Malaysia Pahang Al-Sultan Abdullah" class="medan" @error('org_name') aria-invalid="true" @enderror>
                    @error('org_name')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                </label>

                <label id="orgSizeWrap" class="mt-4 block" @if ($isKlinikOld) hidden @endif>
                    <span id="orgSizeLabel" class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Organization Size') }}</span>
                    <select name="org_size" id="orgSize" class="medan">
                        <option value="">{{ __('Select a range') }}</option>
                    </select>
                    <p class="mt-1.5 text-[11.5px] leading-relaxed text-navy/45">{{ __('Used to recommend a subscription tier — you can change this later.') }}</p>
                </label>

                <label id="ssmWrap" class="mt-4 block" @if ($isKlinikOld) hidden @endif>
                    <span id="ssmLabel" class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Business Registration Number (SSM)') }}</span>
                    <input type="text" name="ssm_number" id="ssm" value="{{ old('ssm_number') }}" placeholder="202501012345" class="medan" inputmode="numeric" maxlength="12" @error('ssm_number') aria-invalid="true" @enderror>
                    <p id="ssmNota" class="mt-1.5 text-[11.5px] leading-relaxed text-navy/45">{{ __('Required for companies. We verify this before approval.') }}</p>
                    @error('ssm_number')<p id="ssmRalat" class="mt-1.5 text-[11.5px] leading-relaxed" style="color:#C4574A">{{ $message }}</p>@else<p id="ssmRalat" hidden class="mt-1.5 text-[11.5px] leading-relaxed" style="color:#C4574A">{{ __('Please enter a valid 12-digit SSM registration number') }}</p>@enderror
                </label>

                <div id="optLocation" class="mt-6" @if ($isKlinikOld) hidden @endif>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="block text-[13.5px] font-medium text-navy/75">{{ __('Primary Location') }} <span class="font-normal text-navy/45">({{ __('Optional') }})</span></span>
                            <p class="mt-1 max-w-[30ch] text-[11.5px] leading-relaxed text-navy/50">{{ __('Add your main campus or office location. You can add more branches later from your dashboard.') }}</p>
                        </div>
                        <button type="button" id="optLocToggle" class="shrink-0 whitespace-nowrap text-[13px] font-medium text-teal">{{ __('+ Add location') }}</button>
                    </div>
                    <div id="optLocFields" class="klinik-extra" hidden>
                        <label class="mt-4 block">
                            <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Location Name') }}</span>
                            <input type="text" name="opt_location[name]" id="optLocName" value="{{ old('opt_location.name') }}" placeholder="Main Campus" class="medan">
                        </label>
                        <label class="mt-4 block">
                            <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Address') }}</span>
                            <input type="text" name="opt_location[address]" id="optAddr1" value="{{ old('opt_location.address') }}" placeholder="Address line" class="medan">
                        </label>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <input type="text" name="opt_location[city]" id="optCity" value="{{ old('opt_location.city') }}" placeholder="City" class="medan">
                            <select name="opt_location[state]" id="optState" class="medan">
                                <option value="">{{ __('State') }}</option>
                                @foreach (['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','W.P. Kuala Lumpur','W.P. Labuan','W.P. Putrajaya'] as $state)
                                    <option @selected(old('opt_location.state') === $state)>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="text" name="opt_location[postcode]" id="optPostcode" value="{{ old('opt_location.postcode') }}" placeholder="Postcode" class="medan mt-3">
                        <input type="hidden" name="opt_location[latitude]" id="optLat">
                        <input type="hidden" name="opt_location[longitude]" id="optLng">

                        <div id="optMapWrap" hidden class="mt-4">
                            <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Pin your location') }}</span>
                            <p class="mb-2.5 text-[11.5px] leading-relaxed text-navy/45">{{ __('We placed the pin near your address — drag it to match your exact location.') }}</p>
                            <div id="optMapCanvas" class="relative h-[220px] w-full overflow-hidden rounded-[14px] border border-navy/10"></div>
                            <div class="mt-2 flex items-center justify-between text-[10.5px] font-medium text-navy/40">
                                <span>{{ __('Drag pin, or click the map, to adjust') }}</span>
                                <span id="optMapCoords">3.1390° N, 101.6869° E</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="klinikExtra" class="klinik-extra" @unless ($isKlinikOld) hidden @endunless>
                    <div>
                        <span class="block text-[13.5px] font-medium text-navy/75">{{ __('Primary Location') }}</span>
                        <p class="mt-1 max-w-[34ch] text-[11.5px] leading-relaxed text-navy/50">{{ __('This is where clients will visit for in-person sessions. You can add more branches later from your dashboard.') }}</p>
                    </div>
                    <label class="mt-4 block">
                        <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Location Name') }}</span>
                        <input type="text" name="location[name]" id="locName" value="{{ old('location.name') }}" placeholder="Klinik Kaunseling Damai — Jalan Tun Razak" class="medan" @error('location.name') aria-invalid="true" @enderror>
                    </label>
                    <label class="mt-4 block">
                        <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Address') }}</span>
                        <input type="text" name="location[address]" id="addr1" value="{{ old('location.address') }}" placeholder="Address line" class="medan" @error('location.address') aria-invalid="true" @enderror>
                    </label>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <input type="text" name="location[city]" id="city" value="{{ old('location.city') }}" placeholder="City" class="medan" @error('location.city') aria-invalid="true" @enderror>
                        <select name="location[state]" id="state" class="medan" @error('location.state') aria-invalid="true" @enderror>
                            <option value="">{{ __('State') }}</option>
                            @foreach (['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','W.P. Kuala Lumpur','W.P. Labuan','W.P. Putrajaya'] as $state)
                                <option @selected(old('location.state') === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="text" name="location[postcode]" id="postcode" value="{{ old('location.postcode') }}" placeholder="Postcode" class="medan mt-3" @error('location.postcode') aria-invalid="true" @enderror>
                    <input type="hidden" name="location[latitude]" id="lat">
                    <input type="hidden" name="location[longitude]" id="lng">

                    <div id="mapWrap" hidden class="mt-4">
                        <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Pin your location') }}</span>
                        <p class="mb-2.5 text-[11.5px] leading-relaxed text-navy/45">{{ __('We placed the pin near your address — drag it to match your exact location.') }}</p>
                        <div id="mapCanvas" class="relative h-[220px] w-full overflow-hidden rounded-[14px] border border-navy/10"></div>
                        <div class="mt-2 flex items-center justify-between text-[10.5px] font-medium text-navy/40">
                            <span>{{ __('Drag pin, or click the map, to adjust') }}</span>
                            <span id="mapCoords">3.1390° N, 101.6869° E</span>
                        </div>
                    </div>

                    <span class="mt-4 mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Upload Clinic Photos') }}</span>
                    <div id="photoDrop" class="cursor-pointer rounded-[12px] border border-dashed border-navy/20 p-5 text-center transition-colors duration-200 hover:border-teal/50">
                        <svg width="22" height="22" class="mx-auto text-navy/35" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                        <p class="mt-2 text-[13px] text-navy/55">{{ __('Drag photos here or') }} <span class="font-medium text-teal">{{ __('click to upload') }}</span></p>
                        <input type="file" name="photos[]" id="photoInput" accept="image/*" multiple hidden>
                    </div>
                    <div id="photoThumbs" class="mt-3 flex flex-wrap gap-2.5"></div>
                    @error('photos')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                    <p class="mt-1.5 text-[11.5px] leading-relaxed text-navy/45">{{ __('Add at least one photo of your clinic. This helps clients recognize your location.') }}</p>
                </div>

                <button type="button" id="s2Continue" class="btn-utama mt-6 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Continue') }}</button>
            </div>
            <button type="button" class="s-back mt-4 inline-flex items-center gap-1.5 text-[13.5px] text-navy/55" data-ke="1"><span aria-hidden="true">←</span> {{ __('Back') }}</button>
        </div>

        <!-- STEP 3 -->
        <div id="step3" class="step-panel" hidden>
            <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Add your email domain') }}</h1>
            <p id="s3Sub" class="mt-2 text-[13.5px] leading-relaxed text-navy/60">{{ __("This lets us automatically verify students and staff who sign up with your organization's email.") }}</p>

            <div class="mt-5 flex gap-2.5 rounded-[10px] bg-navy/[.04] p-3.5">
                <svg width="15" height="15" class="mt-0.5 shrink-0 text-navy/45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 8h.01M11 12h1v5h1"/></svg>
                <p class="text-[12.5px] leading-relaxed text-navy/55">{{ __("Members who sign up with an email ending in your domain will automatically join your organization with the right role.") }}</p>
            </div>

            <label class="mt-5 flex cursor-pointer items-start gap-2.5">
                <input type="checkbox" name="no_domain" id="noDomainChk" value="1" @checked(old('no_domain')) class="mt-0.5 h-[17px] w-[17px] shrink-0 rounded-[5px] border border-navy/25 accent-teal">
                <span class="text-[13.5px] leading-snug text-navy/75">{{ __("I don't have an organization email domain — set up invite codes instead") }}</span>
            </label>

            <div id="domainLapis" class="domain-lapis mt-4" @if (old('no_domain')) hidden style="opacity:0" @endif>
                <div id="domainList" class="flex flex-col gap-3"></div>
                <button type="button" id="addDomain" class="mt-3 text-[13.5px] font-medium text-teal">{{ __('+ Add another domain') }}</button>
                @error('domains')<p class="mt-2 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                @php
                    // Per-row failures (e.g. "this domain is already registered")
                    // land under keys like domains.0.domain, not the plain
                    // "domains" key the block above checks — surface those too,
                    // deduped, so a rejected domain doesn't silently bounce the
                    // user back to this step with no explanation.
                    $domainRowErrors = collect($errors->keys())
                        ->filter(fn ($k) => str_starts_with($k, 'domains.'))
                        ->flatMap(fn ($k) => $errors->get($k))
                        ->unique()
                        ->values();
                @endphp
                @foreach ($domainRowErrors as $domainRowError)
                    <p class="mt-2 text-[11.5px]" style="color:#C4574A">{{ $domainRowError }}</p>
                @endforeach
            </div>

            <div id="noDomainNota" class="domain-lapis mt-4" @unless (old('no_domain')) hidden style="opacity:0" @endunless>
                <div class="rounded-[10px] p-3.5" style="background:rgba(14,42,51,.04)">
                    <p class="text-[13px] leading-relaxed text-navy/65">{{ __("No problem. We'll set up invite codes for your members instead — you can configure this after your account is approved.") }}</p>
                </div>
            </div>

            <div class="mt-5 flex gap-2.5 rounded-[10px] p-3.5" style="background:rgba(217,143,74,.08)">
                <svg width="15" height="15" class="mt-0.5 shrink-0" style="color:#D98F4A" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                <p class="text-[12.5px] leading-relaxed text-navy/60">{{ __("We'll verify domain ownership after you submit this form. You'll receive instructions to confirm you control this domain.") }}</p>
            </div>

            <button type="button" id="s3bContinue" data-siap="0" class="btn-utama mt-6 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF;opacity:.4">{{ __('Continue') }}</button>
            <button type="button" class="s-back mt-4 inline-flex items-center gap-1.5 text-[13.5px] text-navy/55" data-ke="2"><span aria-hidden="true">←</span> {{ __('Back') }}</button>
        </div>

        <!-- STEP 4 -->
        <div id="step4" class="step-panel" hidden>
            <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Set up your admin account') }}</h1>
            <p class="mt-2 text-[13.5px] leading-relaxed text-navy/60">{{ __("You'll be the first administrator for this organization. You can invite more admins later.") }}</p>

            <div class="mt-6">
                <label class="block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Full Name') }}</span>
                    <input type="text" name="admin_name" id="adminName" value="{{ old('admin_name') }}" placeholder="Dr. Nur Syafiqah" class="medan" @error('admin_name') aria-invalid="true" @enderror>
                    @error('admin_name')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                </label>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Job Title / Position') }}</span>
                    <input type="text" name="admin_title" id="adminTitle" value="{{ old('admin_title') }}" placeholder="Head of Counseling Unit" class="medan" @error('admin_title') aria-invalid="true" @enderror>
                    @error('admin_title')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                </label>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Work Email') }}</span>
                    <input type="email" name="admin_email" id="adminEmail" value="{{ old('admin_email') }}" placeholder="name@yourorganization.edu.my" class="medan" @error('admin_email') aria-invalid="true" @enderror>
                    @error('admin_email')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@else<p class="mt-1.5 text-[11.5px] leading-relaxed text-navy/45">{{ __('Use your organization email if possible — this helps us verify your role faster.') }}</p>@enderror
                </label>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Password') }}</span>
                    <span class="relative block">
                        <input type="password" name="admin_password" id="adminPass" placeholder="••••••••" class="medan pr-12" autocomplete="new-password" @error('admin_password') aria-invalid="true" @enderror>
                        <button type="button" class="mata-togol absolute right-1 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-navy/45 transition-colors duration-200 hover:text-navy" data-untuk="adminPass" aria-label="Show password">
                            <svg data-mata="tutup" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.87 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.87 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-mata="buka" hidden width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.64 0 8.57 3.22 9.94 6.65a1 1 0 0 1 0 .7 10.9 10.9 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2.06 11.65a1 1 0 0 0 0 .7A10.75 10.75 0 0 0 12 19a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </span>
                    @error('admin_password')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                </label>
                <ul class="mt-3 flex flex-col gap-2.5 rounded-[10px] p-3" style="background:rgba(14,42,51,.04)">
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="len" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span><span>{{ __('At least 8 characters') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="upper" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span><span>{{ __('Contains one uppercase letter') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="num" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span><span>{{ __('Contains one number') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="spec" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span><span>{{ __('Contains one special character') }}</span>
                    </li>
                </ul>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Confirm Password') }}</span>
                    <span class="relative block">
                        <input type="password" name="admin_password_confirmation" id="adminConfirm" placeholder="••••••••" class="medan pr-12" autocomplete="new-password">
                        <button type="button" class="mata-togol absolute right-1 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-navy/45 transition-colors duration-200 hover:text-navy" data-untuk="adminConfirm" aria-label="Show password">
                            <svg data-mata="tutup" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.87 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.87 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-mata="buka" hidden width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.64 0 8.57 3.22 9.94 6.65a1 1 0 0 1 0 .7 10.9 10.9 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2.06 11.65a1 1 0 0 0 0 .7A10.75 10.75 0 0 0 12 19a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </span>
                    <p id="confirmNota" hidden class="mt-2 flex items-center gap-1.5 text-[12.5px]"></p>
                    @error('admin_password_confirmation')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                </label>

                <label class="mt-4 flex cursor-pointer items-start gap-2.5">
                    <input type="checkbox" name="confirm_authorized" id="chkAuth" value="1" @checked(old('confirm_authorized')) class="mt-0.5 h-[17px] w-[17px] shrink-0 rounded-[5px] border border-navy/25 accent-teal">
                    <span class="text-[13.5px] leading-snug text-navy/75">{{ __('I confirm I am authorized to register this organization on behalf of') }} <strong id="chkOrgName" class="font-semibold text-navy">{{ old('org_name') ?: __('this organization') }}</strong></span>
                </label>
                <label class="mt-3 flex cursor-pointer items-start gap-2.5">
                    <input type="checkbox" name="confirm_terms" id="chkTerms" value="1" @checked(old('confirm_terms')) class="mt-0.5 h-[17px] w-[17px] shrink-0 rounded-[5px] border border-navy/25 accent-teal">
                    <span class="text-[13.5px] leading-snug text-navy/75">{{ __('I agree to the Terms of Service and Privacy Policy') }}</span>
                </label>

                <!-- Summary -->
                <div class="mt-6 rounded-xl p-4" style="background:rgba(14,42,51,.04)">
                    <div class="ringkasan-baris flex items-center justify-between gap-3" style="--tunda:0s">
                        <span class="text-[12.5px] text-navy/50">{{ __('Organization type') }}</span>
                        <span class="flex items-center gap-2 text-[13px] font-medium text-navy"><span id="sumType">—</span><button type="button" class="s-back text-[12px] font-medium text-teal" data-ke="1">{{ __('Edit') }}</button></span>
                    </div>
                    <div class="ringkasan-baris mt-2.5 flex items-center justify-between gap-3" style="--tunda:0.06s">
                        <span id="sumNameLabel" class="text-[12.5px] text-navy/50">{{ __('Organization name') }}</span>
                        <span class="flex items-center gap-2 text-[13px] font-medium text-navy"><span id="sumName">—</span><button type="button" class="s-back text-[12px] font-medium text-teal" data-ke="2">{{ __('Edit') }}</button></span>
                    </div>
                    <div class="ringkasan-baris mt-2.5 flex items-center justify-between gap-3" style="--tunda:0.12s">
                        <span class="text-[12.5px] text-navy/50">{{ __('Access') }}</span>
                        <span id="sumAkses" class="text-[13px] font-medium text-navy">—</span>
                    </div>
                    <div id="sumDomainRow" class="ringkasan-baris mt-2.5 flex items-center justify-between gap-3" style="--tunda:0.18s">
                        <span class="text-[12.5px] text-navy/50">{{ __('Email domain') }}</span>
                        <span class="flex items-center gap-2 text-[13px] font-medium text-navy"><span id="sumDomain">—</span><button type="button" class="s-back text-[12px] font-medium text-teal" data-ke="3">{{ __('Edit') }}</button></span>
                    </div>
                    <div id="sumLocationRow" class="ringkasan-baris mt-2.5 flex items-center justify-between gap-3" style="--tunda:0.18s" hidden>
                        <span class="text-[12.5px] text-navy/50">{{ __('Primary location') }}</span>
                        <span class="flex items-center gap-2 text-[13px] font-medium text-navy"><span id="sumLocation">—</span><button type="button" class="s-back text-[12px] font-medium text-teal" data-ke="2">{{ __('Edit') }}</button></span>
                    </div>
                    <div id="sumSsmRow" class="ringkasan-baris mt-2.5 flex items-center justify-between gap-3" style="--tunda:0.24s" hidden>
                        <span class="text-[12.5px] text-navy/50">{{ __('Business registration') }}</span>
                        <span class="flex items-center gap-2 text-[13px] font-medium text-navy"><span id="sumSsm">—</span><button type="button" class="s-back text-[12px] font-medium text-teal" data-ke="2">{{ __('Edit') }}</button></span>
                    </div>
                </div>

                <button type="submit" id="s3Submit" data-siap="0" class="btn-utama mt-6 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF;opacity:.4">
                    <span data-btn="label">{{ __('Create Organization Account') }}</span>
                    <svg data-btn="spin" hidden class="putar" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>
                </button>
            </div>
            <button type="button" id="step4Back" class="s-back mt-4 inline-flex items-center gap-1.5 text-[13.5px] text-navy/55" data-ke="3"><span aria-hidden="true">←</span> {{ __('Back') }}</button>
        </div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var startStep = {{ $errorStep }};
                var oldDomains = @json(old('domains', []));

                var langkahSemasa = 1;
                var urutanPenuh = [1, 2, 3, 4];
                var urutanKlinik = [1, 2, 4];
                var urutan = urutanPenuh;
                var labelPenuh = ['Organization type', 'Organization info', 'Verify domain', 'Admin account'];
                var labelKlinik = ['Organization type', 'Clinic info', 'Admin account'];
                var labelAktif = labelPenuh;

                var dotsWrap = document.getElementById('dotsWrap');
                var stepLabel = document.getElementById('stepLabel');
                var panels = { 1: document.getElementById('step1'), 2: document.getElementById('step2'), 3: document.getElementById('step3'), 4: document.getElementById('step4') };
                var orgTypeInput = document.getElementById('orgTypeInput');
                var accessModelInput = document.getElementById('accessModelInput');

                function bangunDots() {
                    dotsWrap.innerHTML = urutan.map(function (_, i) { return '<span class="dot" data-pos="' + (i + 1) + '"></span>'; }).join('');
                }

                function paparDots(n) {
                    var pos = urutan.indexOf(n) + 1;
                    [].slice.call(dotsWrap.querySelectorAll('.dot')).forEach(function (d) {
                        var i = +d.getAttribute('data-pos');
                        d.classList.remove('selesai', 'semasa');
                        if (i < pos) d.classList.add('selesai'); else if (i === pos) d.classList.add('semasa');
                    });
                    stepLabel.textContent = 'Step ' + pos + ' of ' + urutan.length + ' — ' + labelAktif[pos - 1];
                }

                function tukar(n, instant) {
                    var lama = panels[langkahSemasa];
                    function selesai() {
                        lama.hidden = true; lama.classList.remove('keluar');
                        langkahSemasa = n;
                        var baru = panels[n];
                        baru.hidden = false;
                        if (!instant) { baru.style.animation = 'none'; void baru.offsetWidth; baru.style.animation = ''; }
                        paparDots(n);
                        window.scrollTo(0, 0);
                        if (n === 4) {
                            document.getElementById('step4Back').setAttribute('data-ke', orgTypeInput.value === 'clinic' ? '2' : '3');
                            kemasKiniRingkasan();
                        }
                    }
                    if (instant) { lama.hidden = true; selesai(); return; }
                    lama.classList.add('keluar');
                    setTimeout(selesai, 220);
                }

                // STEP 1 — org type
                var kad = [].slice.call(document.querySelectorAll('.org-pilih'));
                var s1Btn = document.getElementById('s1Continue');

                function applyOrgType(tipe, akses) {
                    var isKlinik = tipe === 'clinic';
                    var isCorp = tipe === 'corporate';
                    document.getElementById('orgSizeWrap').hidden = isKlinik;
                    document.getElementById('klinikExtra').hidden = !isKlinik;
                    document.getElementById('ssmWrap').hidden = isKlinik;
                    document.getElementById('optLocation').hidden = isKlinik;
                    document.getElementById('ssmLabel').textContent = isCorp ? 'Business Registration Number (SSM)' : 'Business Registration Number (SSM) (optional)';
                    document.getElementById('ssmNota').textContent = isCorp
                        ? 'Required for companies. We verify this before approval.'
                        : 'If your institution is registered with SSM, adding this speeds up approval. Public universities registered under the Ministry of Higher Education can skip this.';
                    document.getElementById('orgNameLabel').textContent = isKlinik ? 'Clinic Name' : 'Organization Name';
                    document.getElementById('orgName').placeholder = isKlinik ? 'Klinik Kaunseling Damai' : 'Universiti Malaysia Pahang Al-Sultan Abdullah';
                    if (isKlinik) initMap();
                    document.getElementById('s2Sub').textContent = isKlinik
                        ? 'This information appears publicly on your counselor listings.'
                        : 'This information appears to your members.';
                    document.getElementById('orgSizeLabel').textContent = isCorp ? 'Company Size' : 'Organization Size';
                    var unit = isCorp ? 'employees' : 'members';
                    var orgSize = document.getElementById('orgSize');
                    var currentSize = orgSize.value;
                    orgSize.innerHTML = '<option value="">Select a range</option>' +
                        ['1–50', '51–200', '201–1,000', '1,000–5,000', '5,000+'].map(function (r) {
                            var label = r + ' ' + unit;
                            return '<option' + (label === currentSize ? ' selected' : '') + '>' + label + '</option>';
                        }).join('');
                    document.getElementById('s3Sub').textContent = isCorp
                        ? "This lets us automatically verify employees who sign up with your organization's email."
                        : "This lets us automatically verify students and staff who sign up with your organization's email.";
                    rolOptions = isCorp ? ['Employee', 'Manager', 'Counselor'] : ['Student', 'Staff', 'Counselor'];
                    [].slice.call(domainList.querySelectorAll('.domain-rol')).forEach(function (sel) {
                        var semasa = sel.value;
                        sel.innerHTML = rolOptions.map(function (r) { return '<option' + (r === semasa ? ' selected' : '') + '>' + r + '</option>'; }).join('');
                        if (sel.value !== semasa) sel.selectedIndex = 0;
                    });
                    urutan = isKlinik ? urutanKlinik : urutanPenuh;
                    labelAktif = isKlinik ? labelKlinik : labelPenuh;
                    bangunDots();
                    orgTypeInput.value = tipe;
                    accessModelInput.value = akses;
                    document.getElementById('sumDomainRow').hidden = isKlinik;
                }

                kad.forEach(function (el) {
                    el.addEventListener('click', function () {
                        kad.forEach(function (o) { o.setAttribute('aria-pressed', o === el ? 'true' : 'false'); });
                        s1Btn.disabled = false;
                    });
                });
                s1Btn.addEventListener('click', function () {
                    var dipilih = document.querySelector('.org-pilih[aria-pressed="true"]');
                    if (!dipilih) return;
                    applyOrgType(dipilih.getAttribute('data-tipe'), dipilih.getAttribute('data-akses'));
                    tukar(2);
                });

                // MAP (real Leaflet + OpenStreetMap, with address geocoding)
                var mapInited = false;
                function initMap() {
                    if (mapInited) return;
                    mapInited = true;
                    window.MyKaunselLocationPicker({
                        addrId: 'addr1', postcodeId: 'postcode', cityId: 'city', stateId: 'state',
                        wrapId: 'mapWrap', canvasId: 'mapCanvas', coordsId: 'mapCoords', latId: 'lat', lngId: 'lng',
                    });
                }
                var optMapInited = false;
                function initOptMap() {
                    if (optMapInited) return;
                    optMapInited = true;
                    window.MyKaunselLocationPicker({
                        addrId: 'optAddr1', postcodeId: 'optPostcode', cityId: 'optCity', stateId: 'optState',
                        wrapId: 'optMapWrap', canvasId: 'optMapCanvas', coordsId: 'optMapCoords', latId: 'optLat', lngId: 'optLng',
                    });
                }

                // STEP 2 — optional location toggle (university/corporate)
                var optLocToggle = document.getElementById('optLocToggle');
                var optLocFields = document.getElementById('optLocFields');
                function toggleOptLocation(expand) {
                    optLocFields.hidden = !expand;
                    optLocToggle.textContent = expand ? 'Skip this step' : '+ Add location';
                    optLocToggle.className = expand ? 'shrink-0 whitespace-nowrap text-[12.5px] text-navy/45' : 'shrink-0 whitespace-nowrap text-[13px] font-medium text-teal';
                    if (expand) initOptMap();
                }
                optLocToggle.addEventListener('click', function () { toggleOptLocation(optLocFields.hidden); });

                // STEP 2 — clinic photo upload
                var photoInput = document.getElementById('photoInput');
                var photoDrop = document.getElementById('photoDrop');
                var photoThumbs = document.getElementById('photoThumbs');

                function syncPhotoInput(files) {
                    var dt = new DataTransfer();
                    files.forEach(function (f) { dt.items.add(f); });
                    photoInput.files = dt.files;
                }
                function renderThumbs(files) {
                    photoThumbs.innerHTML = '';
                    files.forEach(function (f, i) {
                        var url = URL.createObjectURL(f);
                        var el = document.createElement('div');
                        el.className = 'relative h-16 w-16 overflow-hidden rounded-[8px] border border-navy/10';
                        el.innerHTML = '<img src="' + url + '" class="h-full w-full object-cover">' +
                            '<button type="button" data-i="' + i + '" class="foto-buang absolute right-0.5 top-0.5 grid h-5 w-5 place-items-center rounded-full bg-navy/70 text-cream" aria-label="Remove photo">' +
                            '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>';
                        photoThumbs.appendChild(el);
                    });
                    [].slice.call(photoThumbs.querySelectorAll('.foto-buang')).forEach(function (b) {
                        b.addEventListener('click', function () {
                            var current = [].slice.call(photoInput.files);
                            current.splice(+b.getAttribute('data-i'), 1);
                            syncPhotoInput(current);
                            renderThumbs(current);
                        });
                    });
                }
                photoDrop.addEventListener('click', function () { photoInput.click(); });
                photoDrop.addEventListener('dragover', function (e) { e.preventDefault(); });
                photoDrop.addEventListener('drop', function (e) {
                    e.preventDefault();
                    var combined = [].slice.call(photoInput.files).concat([].slice.call(e.dataTransfer.files));
                    syncPhotoInput(combined);
                    renderThumbs(combined);
                });
                photoInput.addEventListener('change', function () { renderThumbs([].slice.call(photoInput.files)); });

                // STEP 2 — continue (client-side sanity checks before moving on)
                document.getElementById('s2Continue').addEventListener('click', function () {
                    var isKlinik = orgTypeInput.value === 'clinic';
                    var nama = document.getElementById('orgName');
                    if (nama.value.trim().length < 2) {
                        nama.setAttribute('aria-invalid', 'true');
                        nama.classList.remove('goyang'); void nama.offsetWidth; nama.classList.add('goyang');
                        nama.focus();
                        return;
                    }
                    nama.removeAttribute('aria-invalid');

                    var isCorp = orgTypeInput.value === 'corporate';
                    var ssm = document.getElementById('ssm');
                    var ssmRalat = document.getElementById('ssmRalat');
                    var ssmOk = /^\d{12}$/.test(ssm.value.trim());
                    if (!isKlinik && ((isCorp && !ssmOk) || (!isCorp && ssm.value.trim().length > 0 && !ssmOk))) {
                        ssm.setAttribute('aria-invalid', 'true');
                        ssmRalat.hidden = false;
                        ssm.classList.remove('goyang'); void ssm.offsetWidth; ssm.classList.add('goyang');
                        return;
                    }
                    ssm.removeAttribute('aria-invalid');
                    if (!isKlinik) ssmRalat.hidden = true;

                    if (isKlinik) {
                        var wajib = [document.getElementById('locName'), document.getElementById('addr1'), document.getElementById('city'), document.getElementById('postcode'), document.getElementById('state')];
                        var semuaOK = true;
                        wajib.forEach(function (el) {
                            var kosong = el.value.trim().length === 0;
                            if (kosong) { semuaOK = false; el.setAttribute('aria-invalid', 'true'); el.classList.remove('goyang'); void el.offsetWidth; el.classList.add('goyang'); }
                            else { el.removeAttribute('aria-invalid'); }
                        });
                        if (photoInput.files.length === 0) {
                            semuaOK = false;
                            photoDrop.classList.remove('goyang'); void photoDrop.offsetWidth; photoDrop.classList.add('goyang');
                        }
                        if (!semuaOK) return;
                        tukar(4);
                        return;
                    }
                    tukar(3);
                });

                // STEP 3 — domain management
                var domainList = document.getElementById('domainList');
                var addDomainBtn = document.getElementById('addDomain');
                var noDomainChk = document.getElementById('noDomainChk');
                var domainLapis = document.getElementById('domainLapis');
                var noDomainNota = document.getElementById('noDomainNota');
                var s3bBtn = document.getElementById('s3bContinue');
                var contohDomain = ['adab.umpsa.edu.my', 'fkee.umpsa.edu.my', 'fkm.umpsa.edu.my'];
                var contohIdx = 0;
                var rolOptions = ['Student', 'Staff', 'Counselor'];
                var domainRowIndex = 0;

                // PHP pairs "domains[][domain]" and "domains[][role]" as two
                // INDEPENDENT auto-incrementing arrays when they're separate
                // field names — not as one row per pair — which silently
                // scrambled every domain/role pairing. Each row needs its
                // own explicit, stable numeric index instead.
                function buatBarisDomain(wajib, placeholder, rolLalai, domainValue) {
                    var idx = domainRowIndex++;
                    var baris = document.createElement('div');
                    baris.className = 'domain-baris flex items-center gap-2.5';
                    var rolHtml = rolOptions.map(function (r) { return '<option' + (r === rolLalai ? ' selected' : '') + '>' + r + '</option>'; }).join('');
                    baris.innerHTML =
                        '<span class="relative flex-1"><span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[14px] text-navy/35">@</span><input type="text" name="domains[' + idx + '][domain]" value="' + (domainValue || '') + '" placeholder="' + placeholder + '" class="medan domain-input w-full" style="padding-left:30px"></span>' +
                        '<select name="domains[' + idx + '][role]" class="medan domain-rol" style="width:132px">' + rolHtml + '</select>' +
                        (wajib ? '' : '<button type="button" class="domain-buang grid h-9 w-9 shrink-0 place-items-center rounded-lg text-navy/40 hover:text-ralat" aria-label="Remove domain">' +
                            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>');
                    baris.querySelector('.domain-input').addEventListener('input', semakDomain);
                    if (!wajib) baris.querySelector('.domain-buang').addEventListener('click', function () {
                        baris.classList.add('keluar');
                        setTimeout(function () { baris.remove(); semakDomain(); }, 180);
                    });
                    return baris;
                }

                if (oldDomains.length) {
                    oldDomains.forEach(function (d, i) {
                        domainList.appendChild(buatBarisDomain(i === 0, d.domain || 'umpsa.edu.my', d.role || rolOptions[0], d.domain));
                    });
                } else {
                    domainList.appendChild(buatBarisDomain(true, 'umpsa.edu.my', rolOptions[0]));
                }

                addDomainBtn.addEventListener('click', function () {
                    var p = contohDomain[contohIdx % contohDomain.length]; contohIdx++;
                    domainList.appendChild(buatBarisDomain(false, p, rolOptions[0]));
                });

                function semakDomain() {
                    var isi = [].slice.call(domainList.querySelectorAll('.domain-input')).some(function (i) { return i.value.trim().length > 2; });
                    var siap = isi || noDomainChk.checked;
                    s3bBtn.dataset.siap = siap ? '1' : '0';
                    s3bBtn.style.opacity = siap ? '1' : '.4';
                }

                // A `hidden` wrapper only hides fields visually — the browser
                // still submits them, so an empty leftover domain row was
                // sneaking into the request and failing validation even
                // after checking "no domain". Disabling them is what
                // actually excludes them from the form submission.
                function setDomainFieldsDisabled(disabled) {
                    [].slice.call(domainList.querySelectorAll('input, select')).forEach(function (el) {
                        el.disabled = disabled;
                    });
                }

                noDomainChk.addEventListener('change', function () {
                    if (noDomainChk.checked) {
                        setDomainFieldsDisabled(true);
                        domainLapis.style.opacity = '0';
                        setTimeout(function () {
                            domainLapis.hidden = true;
                            noDomainNota.hidden = false;
                            requestAnimationFrame(function () { noDomainNota.style.opacity = '1'; });
                        }, 200);
                    } else {
                        setDomainFieldsDisabled(false);
                        noDomainNota.style.opacity = '0';
                        setTimeout(function () {
                            noDomainNota.hidden = true;
                            domainLapis.hidden = false;
                            requestAnimationFrame(function () { domainLapis.style.opacity = '1'; });
                        }, 200);
                    }
                    semakDomain();
                });

                if (noDomainChk.checked) setDomainFieldsDisabled(true);

                s3bBtn.addEventListener('click', function () {
                    if (s3bBtn.dataset.siap !== '1') {
                        [].slice.call(domainList.querySelectorAll('.domain-input')).forEach(function (i) {
                            if (i.value.trim().length <= 2) { i.setAttribute('aria-invalid', 'true'); i.classList.remove('goyang'); void i.offsetWidth; i.classList.add('goyang'); }
                        });
                        return;
                    }
                    tukar(4);
                });

                // Back links
                [].slice.call(document.querySelectorAll('.s-back')).forEach(function (b) {
                    b.addEventListener('click', function () { tukar(+b.getAttribute('data-ke')); });
                });

                // STEP 4 — password toggles
                [].slice.call(document.querySelectorAll('.mata-togol')).forEach(function (b) {
                    var medan = document.getElementById(b.getAttribute('data-untuk'));
                    var t = b.querySelector('[data-mata="tutup"]'), o = b.querySelector('[data-mata="buka"]');
                    b.addEventListener('click', function () {
                        var lihat = medan.type === 'password';
                        medan.type = lihat ? 'text' : 'password';
                        t.hidden = lihat; o.hidden = !lihat;
                    });
                });

                var pass = document.getElementById('adminPass');
                var confirmEl = document.getElementById('adminConfirm');
                var syarat = [].slice.call(document.querySelectorAll('#step4 .rp-syarat'));
                var nota = document.getElementById('confirmNota');
                var chkAuth = document.getElementById('chkAuth');
                var chkTerms = document.getElementById('chkTerms');
                var s3Btn = document.getElementById('s3Submit');
                var nameEl = document.getElementById('adminName');
                var titleEl = document.getElementById('adminTitle');
                var emailEl = document.getElementById('adminEmail');
                var tundaConfirm = null;
                var uji = {
                    len: function (v) { return v.length >= 8; },
                    upper: function (v) { return /[A-Z]/.test(v); },
                    num: function (v) { return /[0-9]/.test(v); },
                    spec: function (v) { return /[^A-Za-z0-9]/.test(v); }
                };

                function semakPass() {
                    var v = pass.value, semuaOK = true;
                    syarat.forEach(function (li) {
                        var k = li.getAttribute('data-syarat'), lulus = uji[k](v);
                        if (!lulus) semuaOK = false;
                        li.setAttribute('data-keadaan', v.length === 0 ? 'kosong' : (lulus ? 'ok' : 'gagal'));
                    });
                    return semuaOK;
                }
                function semakConfirm(lambat) {
                    if (confirmEl.value.length === 0) { nota.hidden = true; confirmEl.removeAttribute('aria-invalid'); return false; }
                    if (confirmEl.value === pass.value) {
                        nota.hidden = false; nota.style.color = '#0F6B7D';
                        nota.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg> Passwords match';
                        confirmEl.removeAttribute('aria-invalid');
                        return true;
                    }
                    if (!lambat) {
                        nota.hidden = false; nota.style.color = 'rgba(14,42,51,.55)'; nota.textContent = 'Keep typing...';
                        return false;
                    }
                    nota.hidden = false; nota.style.color = '#C4574A';
                    nota.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Passwords don\'t match';
                    confirmEl.setAttribute('aria-invalid', 'true');
                    return false;
                }
                function semakSemua() {
                    var passOK = semakPass();
                    var confirmOK = confirmEl.value.length > 0 && confirmEl.value === pass.value;
                    var ok = passOK && confirmOK && nameEl.value.trim().length > 1 && titleEl.value.trim().length > 1
                        && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim()) && chkAuth.checked && chkTerms.checked;
                    s3Btn.dataset.siap = ok ? '1' : '0';
                    s3Btn.style.opacity = ok ? '1' : '.4';
                }
                pass.addEventListener('input', function () { semakPass(); semakConfirm(false); semakSemua(); });
                confirmEl.addEventListener('input', function () {
                    semakConfirm(false); semakSemua();
                    clearTimeout(tundaConfirm);
                    tundaConfirm = setTimeout(function () { semakConfirm(true); }, 400);
                });
                [nameEl, titleEl, emailEl].forEach(function (el) { el.addEventListener('input', semakSemua); });
                [chkAuth, chkTerms].forEach(function (el) { el.addEventListener('change', semakSemua); });

                function kemasKiniRingkasan() {
                    var labelTipe = { university: 'University / Higher Education', corporate: 'Company / Corporate', clinic: 'Clinic / Private Practice' };
                    var isKlinik = orgTypeInput.value === 'clinic';
                    document.getElementById('sumType').textContent = labelTipe[orgTypeInput.value] || '—';
                    document.getElementById('sumNameLabel').textContent = isKlinik ? 'Clinic name' : 'Organization name';
                    document.getElementById('sumName').textContent = document.getElementById('orgName').value || '—';
                    document.getElementById('chkOrgName').textContent = document.getElementById('orgName').value || 'this organization';
                    document.getElementById('sumAkses').textContent = accessModelInput.value === 'open' ? 'Open to all platform users' : 'Members only';

                    var hasLocation = isKlinik
                        ? document.getElementById('city').value.trim().length > 0
                        : (!document.getElementById('optLocFields').hidden && document.getElementById('optCity').value.trim().length > 0);
                    document.getElementById('sumLocationRow').hidden = !hasLocation;
                    if (hasLocation) {
                        var city = isKlinik ? document.getElementById('city').value : document.getElementById('optCity').value;
                        var state = isKlinik ? document.getElementById('state').value : document.getElementById('optState').value;
                        document.getElementById('sumLocation').textContent = [city, state].filter(Boolean).join(', ') || '—';
                    }

                    var ssmVal = document.getElementById('ssm').value.trim();
                    document.getElementById('sumSsmRow').hidden = isKlinik || !ssmVal;
                    document.getElementById('sumSsm').textContent = ssmVal || '—';

                    if (!isKlinik) {
                        var domains = [].slice.call(domainList.querySelectorAll('.domain-input')).map(function (i) { return i.value.trim(); }).filter(Boolean);
                        document.getElementById('sumDomain').textContent = (domains.length && !noDomainChk.checked) ? domains.join(', ') : 'Invite codes';
                    }
                }

                document.getElementById('orgSignupForm').addEventListener('submit', function (e) {
                    if (langkahSemasa !== 4) { e.preventDefault(); return; }
                    if (s3Btn.dataset.siap !== '1') {
                        e.preventDefault();
                        [pass, confirmEl, nameEl, titleEl, emailEl].forEach(function (el) {
                            if (!el.value.trim()) { el.setAttribute('aria-invalid', 'true'); el.classList.remove('goyang'); void el.offsetWidth; el.classList.add('goyang'); }
                        });
                        [chkAuth, chkTerms].forEach(function (chk) {
                            if (!chk.checked) {
                                var wrap = chk.closest('label');
                                wrap.classList.remove('goyang'); void wrap.offsetWidth; wrap.classList.add('goyang');
                            }
                        });
                        return;
                    }
                    var lbl = s3Btn.querySelector('[data-btn="label"]'), spin = s3Btn.querySelector('[data-btn="spin"]');
                    s3Btn.disabled = true; s3Btn.style.opacity = '1'; lbl.hidden = true; spin.hidden = false;
                });

                // Resume at the step where server-side validation failed, restoring visible state.
                if (orgTypeInput.value) {
                    var matchedCard = document.querySelector('.org-pilih[data-tipe="' + orgTypeInput.value + '"]');
                    if (matchedCard) matchedCard.setAttribute('aria-pressed', 'true');
                    s1Btn.disabled = false;
                    applyOrgType(orgTypeInput.value, accessModelInput.value);
                    if (!document.getElementById('optLocFields').hidden || document.getElementById('optCity').value) {
                        toggleOptLocation(true);
                    }
                    if (orgTypeInput.value === 'clinic') initMap();
                }
                if (startStep !== 1) {
                    tukar(startStep, true);
                } else {
                    bangunDots();
                    paparDots(1);
                }
            });
        </script>
    @endpush
</x-auth-wizard-layout>
