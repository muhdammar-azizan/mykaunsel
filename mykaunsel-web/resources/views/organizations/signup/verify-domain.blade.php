@push('styles')
    <style>
        .masuk { animation: masuk .5s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .vd-card { transition: border-color .3s ease, background-color .3s ease; overflow: hidden; }
        .vd-lencana { transition: background-color .3s ease, color .3s ease; }
        .vd-putar { animation: putar 1s linear infinite; }
        .vd-body { transition: max-height .4s ease-out, opacity .4s ease-out; overflow: hidden; }
        .vd-tooltip { transition: opacity .15s ease; }
        #vdModalBg { animation: masukModalBg .2s ease both; }
        @keyframes masukModalBg { from { opacity: 0; } to { opacity: 1; } }
        #vdModal { animation: masukModal .25s ease both; }
        @keyframes masukModal { from { opacity: 0; transform: scale(.95); } to { opacity: 1; transform: none; } }
        .vd-it-panel { transition: max-height .25s ease, opacity .25s ease; overflow: hidden; }
        .vd-dns-block { transition: opacity .22s ease; }
        .vd-verified-row { transition: opacity .3s ease; }
        .vd-check, .vd-it { transition: background-color .2s ease, color .2s ease, border-color .2s ease, transform .2s ease, box-shadow .2s ease; }
        .vd-check { background: #FAF8F5; color: #0F6B7D; border: 1px solid rgba(15,107,125,.35); }
        .vd-it { background: #FAF8F5; color: #D98F4A; border: 1px solid rgba(217,143,74,.35); }
        .vd-check:hover:not(:disabled) { background: #0F6B7D; color: #FAF8F5; border-color: #0F6B7D; transform: translateY(-2px); box-shadow: 0 10px 22px -10px rgba(15,107,125,.45); }
        .vd-it:hover:not(:disabled) { background: #D98F4A; color: #FAF8F5; border-color: #D98F4A; transform: translateY(-2px); box-shadow: 0 10px 22px -10px rgba(217,143,74,.45); }
        @media (prefers-reduced-motion: reduce) {
            .masuk, .vd-card { animation: none !important; opacity: 1 !important; transform: none !important; }
            .vd-putar { animation: none; }
            .vd-check:hover, .vd-it:hover { transform: none; }
        }
    </style>
@endpush

<x-auth-wizard-layout
    title="Verify Domain — MyKaunsel"
    image-slot="org-signup-visual"
    aside-headline="Bring verified counseling to your institution."
    aside-subtext="Manage counselors, availability, and bookings — all in one place, built for how counseling centers actually work."
    content-max-width="460px"
>
    <div class="mb-8">
        <div class="flex items-center gap-2">
            <span class="dot" style="background:#0F6B7D"></span>
            <span class="dot" style="background:#0F6B7D"></span>
            <span class="dot" style="background:#0E2A33"></span>
            <span class="dot" style="background:rgba(14,42,51,.15)"></span>
        </div>
        <p class="mt-2.5 text-[12.5px] font-medium uppercase tracking-[0.1em] text-navy/45">{{ __('Step 3 of 4 — Verify Domain') }}</p>
    </div>

    <div class="masuk text-left">
        <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Verify your domain') }}</h1>
        <p class="mt-2 max-w-[46ch] text-[13.5px] leading-relaxed text-navy/60">{{ __('Add a DNS record to confirm you own this domain. This helps us keep your organization secure.') }}</p>
    </div>

    <div id="domainCards" class="mt-7 flex flex-col gap-4">
        @foreach ($domains as $index => $domain)
            <div class="vd-card rounded-2xl border border-navy/12 bg-cream p-7" data-status="{{ $domain->dns_verified ? 'verified' : 'pending' }}" data-domain-id="{{ $domain->id }}" style="animation: masuk .45s cubic-bezier(.32,.72,0,1) both; animation-delay: {{ $index * 0.1 }}s">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-[18px] font-semibold text-navy">{{ $domain->domain }}</span>
                    <span class="vd-lencana-slot"></span>
                </div>
                <div class="vd-body mt-5">
                    <p class="vd-verified-row flex items-center gap-1.5 text-[13.5px] font-medium" style="color:#0F6B7D" hidden>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                        <span class="vd-verified-date"></span>
                    </p>
                    <div class="vd-dns-block">
                        <div class="rounded-[10px] p-5" style="background:#EDEDED">
                            <p class="text-[10.5px] uppercase tracking-[0.16em] text-navy/45">{{ __('Add this TXT record to your DNS') }}</p>
                            <div class="mt-3 grid gap-1.5 font-mono text-[13px] text-navy" style="grid-template-columns:60px 1fr">
                                <span class="text-navy/45">Type</span><span>TXT</span>
                                <span class="text-navy/45">Name</span><span>@</span>
                                <span class="text-navy/45">Value</span>
                                <span class="flex items-center gap-2 break-all">
                                    <span class="vd-token">{{ $domain->verification_token }}</span>
                                    <span class="relative">
                                        <button type="button" class="vd-copy text-navy/50 hover:text-navy" aria-label="Copy value">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        </button>
                                        <span class="vd-tooltip pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 rounded-md bg-navy px-2 py-1 text-[11px] text-cream opacity-0">{{ __('Copied!') }}</span>
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <button type="button" class="vd-check flex h-[40px] flex-1 items-center justify-center gap-1.5 rounded-full text-[13px] font-medium">
                                <span class="vd-check-label">{{ __('Check now') }}</span>
                            </button>
                            <button type="button" class="vd-it flex h-[40px] flex-1 items-center justify-center gap-1.5 rounded-full text-[13px] font-medium">
                                <svg width="14" height="14" class="shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3 7 8.24 5.5a1.4 1.4 0 0 0 1.52 0L21 7"/></svg>
                                <span>{{ __('Send instructions to IT') }}</span>
                            </button>
                        </div>
                        <div class="vd-it-panel mt-3" style="max-height:0;opacity:0">
                            <div class="flex gap-2">
                                <input type="email" placeholder="it@yourorganization.edu.my" class="medan vd-it-email flex-1">
                                <button type="button" class="vd-it-send rounded-full px-4 text-[13px] font-medium text-white" style="background:#4A7DFF">{{ __('Send') }}</button>
                            </div>
                            <p class="vd-it-sent mt-2 hidden items-center gap-1.5 text-[12.5px] font-medium" style="color:#0F6B7D">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                                <span class="vd-it-sent-text"></span>
                            </p>
                        </div>
                    </div>
                    <p class="vd-note mt-4 text-[11px] leading-relaxed text-navy/45">{{ __("DNS changes can take a few hours to appear. Come back and check again once you've added the record.") }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-7">
        <p id="vdHint" class="mb-2.5 text-[12.5px] leading-relaxed text-navy/50">{{ __('You can continue once all domains are verified — or skip this for now and verify later from your dashboard.') }}</p>

        <form id="vdContinueForm" method="POST" action="{{ route('organizations.signup.verify-domain.continue', $organization) }}">
            @csrf
            <button type="submit" id="vdContinue" data-siap="0" class="btn-utama grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF;opacity:.4">{{ __('Continue') }}</button>
        </form>
        <div class="mt-3 text-center">
            <a href="#" id="vdSkip" class="text-[13px] font-medium text-teal">{{ __("Skip for now, I'll verify later →") }}</a>
        </div>
    </div>

    <!-- Skip confirmation modal -->
    <div id="vdModalWrap" hidden class="fixed inset-0 z-50 grid place-items-center p-6">
        <div id="vdModalBg" class="absolute inset-0" style="background:rgba(14,42,51,.45)"></div>
        <div id="vdModal" class="relative w-full max-w-[400px] rounded-2xl bg-cream p-7" style="box-shadow:0 30px 70px -20px rgba(14,42,51,.35)">
            <h2 class="text-[19px] font-semibold tracking-tightest">{{ __('Continue without verification?') }}</h2>
            <p class="mt-3 text-[13.5px] leading-relaxed text-navy/60">{{ __('Your organization will be reviewed manually by our team, which may take longer. You can verify your domain anytime from your dashboard settings.') }}</p>
            <div class="mt-6 flex gap-3">
                <button type="button" id="vdGoBack" class="grid h-[42px] flex-1 place-items-center rounded-full border border-navy/20 text-[13.5px] font-medium text-navy">{{ __('Go back') }}</button>
                <button type="submit" form="vdContinueForm" id="vdAnyway" class="btn-utama grid h-[42px] flex-1 place-items-center rounded-full text-[13.5px] font-medium text-white" style="background:#4A7DFF">{{ __('Continue anyway') }}</button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                var checkUrlTemplate = @json(route('organizations.signup.verify-domain.check', ['organization' => $organization, 'domain' => '__DOMAIN__']));

                function vdLencanaHtml(status) {
                    var map = {
                        pending: { bg: 'rgba(217,143,74,.12)', fg: '#D98F4A', label: 'Pending verification', icon: '' },
                        checking: { bg: 'rgba(74,125,255,.12)', fg: '#4A7DFF', label: 'Checking...', icon: '<svg class="vd-putar" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>' },
                        verified: { bg: 'rgba(15,107,125,.12)', fg: '#0F6B7D', label: 'Verified', icon: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>' },
                        failed: { bg: 'rgba(196,87,74,.12)', fg: '#C4574A', label: 'Not found yet', icon: '' }
                    };
                    var s = map[status];
                    return '<span class="vd-lencana inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12px] font-medium" style="background:' + s.bg + ';color:' + s.fg + '">' + s.icon + s.label + '</span>';
                }

                function vdSetStatus(card, status, verifiedDate) {
                    card.setAttribute('data-status', status);
                    card.querySelector('.vd-lencana-slot').innerHTML = vdLencanaHtml(status);
                    if (status === 'verified') {
                        card.style.borderColor = 'rgba(15,107,125,.5)';
                        card.style.background = 'rgba(15,107,125,.03)';
                        var dnsBlock = card.querySelector('.vd-dns-block');
                        var verifiedRow = card.querySelector('.vd-verified-row');
                        verifiedRow.querySelector('.vd-verified-date').textContent = 'Verified' + (verifiedDate ? ' on ' + verifiedDate : '');
                        dnsBlock.style.opacity = '0';
                        setTimeout(function () {
                            dnsBlock.hidden = true;
                            card.querySelector('.vd-note').hidden = true;
                            verifiedRow.hidden = false;
                            verifiedRow.style.opacity = '0';
                            setTimeout(function () { verifiedRow.style.opacity = '1'; }, 20);
                        }, 220);
                    }
                    vdSemakSemua();
                }

                function vdWireCard(card) {
                    var checkBtn = card.querySelector('.vd-check');
                    var domainId = card.getAttribute('data-domain-id');
                    checkBtn.addEventListener('click', function () {
                        vdSetStatus(card, 'checking');
                        checkBtn.disabled = true;
                        fetch(checkUrlTemplate.replace('__DOMAIN__', domainId), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                checkBtn.disabled = false;
                                if (data.verified) {
                                    var date = data.checked_at ? new Date(data.checked_at).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
                                    vdSetStatus(card, 'verified', date);
                                } else {
                                    vdSetStatus(card, 'failed');
                                }
                            })
                            .catch(function () {
                                checkBtn.disabled = false;
                                vdSetStatus(card, 'failed');
                            });
                    });

                    var copyBtn = card.querySelector('.vd-copy');
                    copyBtn.addEventListener('click', function () {
                        var val = card.querySelector('.vd-token').textContent;
                        if (navigator.clipboard) navigator.clipboard.writeText(val).catch(function () {});
                        var tip = card.querySelector('.vd-tooltip');
                        tip.style.opacity = '1';
                        setTimeout(function () { tip.style.opacity = '0'; }, 1500);
                    });

                    var itBtn = card.querySelector('.vd-it');
                    var itPanel = card.querySelector('.vd-it-panel');
                    itBtn.addEventListener('click', function () {
                        var open = itPanel.style.maxHeight !== '0px';
                        itPanel.style.maxHeight = open ? '0' : '120px';
                        itPanel.style.opacity = open ? '0' : '1';
                    });
                    card.querySelector('.vd-it-send').addEventListener('click', function () {
                        var email = card.querySelector('.vd-it-email');
                        if (!email.value.trim()) { email.classList.remove('goyang'); void email.offsetWidth; email.classList.add('goyang'); return; }
                        var sent = card.querySelector('.vd-it-sent');
                        sent.querySelector('.vd-it-sent-text').textContent = 'Instructions sent to ' + email.value.trim();
                        sent.classList.remove('hidden'); sent.classList.add('flex');
                        setTimeout(function () {
                            itPanel.style.maxHeight = '0'; itPanel.style.opacity = '0';
                            sent.classList.add('hidden'); sent.classList.remove('flex');
                            email.value = '';
                        }, 3000);
                    });
                }

                function vdSemakSemua() {
                    var cards = [].slice.call(document.querySelectorAll('.vd-card'));
                    var semuaOK = cards.length > 0 && cards.every(function (c) { return c.getAttribute('data-status') === 'verified'; });
                    var btn = document.getElementById('vdContinue');
                    var hint = document.getElementById('vdHint');
                    btn.dataset.siap = semuaOK ? '1' : '0';
                    btn.style.opacity = semuaOK ? '1' : '.4';
                    hint.hidden = semuaOK;
                    document.getElementById('vdSkip').closest('div').hidden = semuaOK;
                }

                [].slice.call(document.querySelectorAll('.vd-card')).forEach(function (card) {
                    var initialStatus = card.getAttribute('data-status');
                    card.querySelector('.vd-lencana-slot').innerHTML = vdLencanaHtml(initialStatus);
                    if (initialStatus === 'verified') {
                        card.style.borderColor = 'rgba(15,107,125,.5)';
                        card.style.background = 'rgba(15,107,125,.03)';
                        card.querySelector('.vd-dns-block').hidden = true;
                        card.querySelector('.vd-note').hidden = true;
                        var row = card.querySelector('.vd-verified-row');
                        row.querySelector('.vd-verified-date').textContent = 'Verified';
                        row.hidden = false;
                    }
                    vdWireCard(card);
                });
                vdSemakSemua();

                document.getElementById('vdContinue').addEventListener('click', function (e) {
                    if (this.dataset.siap !== '1') e.preventDefault();
                });
                document.getElementById('vdSkip').addEventListener('click', function (e) {
                    e.preventDefault();
                    document.getElementById('vdModalWrap').hidden = false;
                });
                document.getElementById('vdGoBack').addEventListener('click', function () {
                    document.getElementById('vdModalWrap').hidden = true;
                });
            });
        </script>
    @endpush
</x-auth-wizard-layout>
