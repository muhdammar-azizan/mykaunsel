@php
    // The landing page (design: LandingPage.html). Every button goes to a real page; sign-up for individuals is in the mobile app (config/registration.php).
    $appUrl = config('registration.mobile_app_url');
    $signup = route('register.type');

    $tick = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>';

    // Section "Satu platform": title, text, link label, placeholder picture caption, tint.
    $steps = [
        ['Perbualan awal', 'Chatbot AI menjadi titik masuk yang paling rendah halangannya. Pengguna menaip apa yang dirasai, dan sistem membantu menyusun keadaan tersebut menjadi langkah yang boleh diambil.', 'Ketahui cara chatbot berfungsi', 'GAMBAR: perbualan chatbot', 'rgba(15,107,125,.10)'],
        ['Kaunselor disahkan', 'Setiap kaunselor disemak terhadap pendaftaran Lembaga Kaunselor Malaysia, termasuk lesen amalan yang masih berkuat kuasa. Profil yang gagal disemak tidak dipaparkan.', 'Ketahui proses pengesahan', 'GAMBAR: pengesahan kaunselor', 'rgba(217,143,74,.14)'],
        ['Tempahan sesi', 'Tapis kaunselor mengikut bidang, bahasa dan lokasi. Pilih slot yang tersedia, tetapkan mod sesi dalam talian atau bersemuka, dan terima pengesahan serta-merta.', 'Lihat cara tempahan', 'GAMBAR: skrin tempahan', 'rgba(15,107,125,.16)'],
        ['Planner kaunselor', 'Satu kalendar untuk slot yang dibuka, sesi yang diterima, dan komitmen peribadi. Kaunselor mengawal ketersediaan mereka tanpa pertindihan jadual.', 'Ketahui tentang planner', 'GAMBAR: planner kaunselor', 'rgba(217,143,74,.09)'],
        ['Portal organisasi', 'Universiti dan klinik menambah kaunselor, menetapkan polisi akses, dan memantau rekod tempahan perkhidmatan mereka dari satu tempat.', 'Ketahui tentang portal', 'GAMBAR: portal organisasi', 'rgba(14,42,51,.08)'],
    ];

    $faq = [
        ['Adakah kaunselor dalam MyKaunsel berdaftar?', 'Ya. Setiap kaunselor disahkan terhadap pendaftaran Lembaga Kaunselor Malaysia, termasuk lesen amalan yang sah, sebelum profil mereka dipaparkan.'],
        ['Apa yang berlaku kepada perbualan saya dengan chatbot?', 'Perbualan chat adalah peribadi. Ia tidak dikongsi dengan kaunselor atau organisasi, dan data disimpan mengikut Akta Perlindungan Data Peribadi (PDPA).'],
        ['Bolehkah saya memilih sesi bersemuka?', 'Boleh. Semasa menempah, anda memilih mod sesi — dalam talian atau bersemuka — berdasarkan slot yang dibuka oleh kaunselor.'],
        ['Bagaimana organisasi mendaftar?', 'Organisasi mendaftar melalui borang pendaftaran organisasi, kemudian menambah kaunselor mereka ke dalam portal pengurusan untuk membuka tempahan.'],
        ['Apa yang organisasi dapat lihat tentang pengguna?', 'Portal organisasi memaparkan maklumat tempahan dan jadual sahaja. Kandungan perbualan chat tidak boleh diakses oleh organisasi.'],
        ['Saya seorang kaunselor. Apa yang saya perlukan untuk menyertai?', 'Anda perlu memberikan maklumat pendaftaran dan lesen amalan anda untuk disemak. Setelah disahkan, anda boleh membuka slot dan menguruskan sesi melalui planner.'],
        ['Berapakah kos menggunakan MyKaunsel?', 'Aplikasi percuma untuk dimuat turun dan chatbot boleh digunakan tanpa kos. Bayaran hanya dikenakan untuk sesi yang ditempah dengan kaunselor bebas atau klinik, mengikut kadar yang ditetapkan kaunselor tersebut. Ahli organisasi biasanya mendapat sesi kaunselor institusi tanpa kos.'],
        ['Bolehkah saya guna MyKaunsel jika universiti saya belum menyertai?', 'Boleh. Anda mendaftar sebagai pengguna awam dan boleh menempah sesi dengan kaunselor bebas serta klinik yang tersedia di platform.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ asset('images/mykaunsel-icon.png') }}">
<title>MyKaunsel — Sokongan kaunseling yang disahkan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  [x-cloak] { display: none !important; }
  html { scroll-behavior: smooth; }
  body { -webkit-font-smoothing: antialiased; background: #FAF8F5; }
  .reveal { opacity: 0; transform: translateY(28px); transition: opacity .6s ease-out, transform .6s ease-out; }
  .reveal.is-in { opacity: 1; transform: none; }
  .no-anim .reveal { opacity: 1 !important; transform: none !important; transition: none; }
  .grain {
    background-image: radial-gradient(circle at 20% 15%, rgba(15,107,125,.22), transparent 55%),
                      radial-gradient(circle at 85% 70%, rgba(217,143,74,.16), transparent 50%);
  }
  ::selection { background: #0F6B7D; color: #fff; }
  .phone-shadow { box-shadow: 0 40px 80px -30px rgba(14,42,51,.45), 0 8px 24px -12px rgba(14,42,51,.25); }
  .step-panel { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .45s ease-out; }
  .step-panel.is-open { grid-template-rows: 1fr; }
  .step-title { transition: color .3s ease-out; }
  .step-visual { opacity: 0; transform: scale(.9) translateY(14px); transform-origin: 50% 60%;
    transition: opacity .34s ease-out, transform .52s cubic-bezier(.34,1.4,.5,1); }
  .step-visual.is-shown { opacity: 1; transform: scale(1) translateY(0); }
  .step-img:not([src]), .step-img[src=""] { display: none; }
  .step-btn:focus-visible { outline: 2px solid #0F6B7D; outline-offset: 4px; border-radius: 6px; }
  @media (prefers-reduced-motion: reduce) {
    .step-panel, .step-title, .step-visual { transition: none; }
    .step-visual { transform: none; }
  }
  .hdr { transition: background-color .35s ease, border-color .35s ease, box-shadow .35s ease; }
  .hdr-hero:not(.hdr-open) { background: transparent !important; border-color: transparent !important; backdrop-filter: none !important; -webkit-backdrop-filter: none !important; }
  .hdr-mark, .hdr-mark span { transition: color .35s ease; }
  .hdr-hero:not(.hdr-open) .hdr-mark, .hdr-hero:not(.hdr-open) .hdr-mark span { color: #FAF8F5; }
  .hdr-hero:not(.hdr-open) .nav-text { color: rgba(250,248,245,.75); }
  .hdr-hero:not(.hdr-open) .nav-glass:hover .nav-text, .hdr-hero:not(.hdr-open) .nav-glass.is-active .nav-text { color: #FAF8F5; }
  .hdr-hero:not(.hdr-open) .nav-pill { background: rgba(250,248,245,.12); border-color: rgba(250,248,245,.25); }
  .hdr-login, .hdr-signup, .hdr-line, .hdr-bar { transition: color .35s ease, background-color .35s ease, border-color .35s ease; }
  .hdr-hero:not(.hdr-open) .hdr-login { color: rgba(250,248,245,.8); }
  .hdr-hero:not(.hdr-open) .hdr-login:hover { color: #FAF8F5; }
  .hdr-hero:not(.hdr-open) .hdr-line { background: rgba(250,248,245,.22); }
  .hdr-hero:not(.hdr-open) .hdr-bar { background: #FAF8F5; }
  .nav-glass { position: relative; display: inline-flex; align-items: center; padding: 10px 16px; border-radius: 9999px; isolation: isolate; }
  .nav-text { position: relative; z-index: 2; color: rgba(14,42,51,.7); transition: color .25s ease-out; }
  .nav-pill {
    position: absolute; inset: 0; z-index: 1; border-radius: 9999px; overflow: hidden;
    background: rgba(250,248,245,.9);
    border: 1px solid rgba(250,248,245,.6);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.7), 0 6px 18px -8px rgba(14,42,51,.22);
    opacity: 0; transform: scale(.94);
    transition: opacity .25s ease-out, transform .25s ease-out;
  }
  @supports (backdrop-filter: blur(12px)) {
    .nav-pill { background: rgba(250,248,245,.55); backdrop-filter: blur(12px) saturate(140%); -webkit-backdrop-filter: blur(12px) saturate(140%); }
  }
  .nav-glass:hover .nav-pill, .nav-glass.is-active .nav-pill {
    opacity: 1; transform: scale(1);
    transition: opacity .32s cubic-bezier(.22,1,.36,1), transform .32s cubic-bezier(.22,1,.36,1);
  }
  .nav-glass:hover .nav-text, .nav-glass.is-active .nav-text { color: #0E2A33; }
  .nav-shine {
    position: absolute; top: 0; bottom: 0; left: 0; width: 40%;
    background: linear-gradient(110deg, transparent 0%, rgba(255,255,255,.45) 50%, transparent 100%);
    mix-blend-mode: overlay; opacity: 0; transform: translateX(-160%);
  }
  .nav-glass:hover .nav-shine { animation: navKilat .7s ease-out 1 both; }
  @keyframes navKilat {
    from { opacity: 1; transform: translateX(-160%); }
    to   { opacity: 1; transform: translateX(360%); }
  }
  @media (prefers-reduced-motion: reduce) {
    .nav-pill, .nav-shine { display: none; }
    .nav-text { transition: none; }
  }
  .lencana { transition: transform .25s ease-out; }
  .lencana:hover { transform: translateY(-2px); }
  .-rotate-4 { transform: rotate(-4deg); }
  @media (prefers-reduced-motion: reduce) { .lencana { transition: none; } .lencana:hover { transform: none; } .reveal { opacity: 1; transform: none; transition: none; } }

  /* FAQ answers open and close smoothly (same trick as the platform steps). */
  .faq-panel { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .35s ease-out; }
  .faq-panel.is-open { grid-template-rows: 1fr; }
  @media (prefers-reduced-motion: reduce) { .faq-panel { transition: none; } }
</style>
</head>
<body class="font-sans text-navy antialiased">

<!-- 1. NAV -->
<header id="hdr" x-data="{ open: false }"
        class="hdr hdr-hero fixed top-0 inset-x-0 z-50 border-b border-navy/10 bg-cream/95 backdrop-blur"
        x-bind:class="open ? 'hdr-open' : ''">
  <div class="mx-auto max-w-7xl px-6 lg:px-10">
    <div class="flex h-20 items-center justify-between">
      <a href="#top" class="flex items-center gap-2.5" aria-label="MyKaunsel">
        <img src="{{ asset('images/mykaunsel-icon.png') }}" alt="" class="h-9 w-auto shrink-0">
        <span class="hdr-mark text-[19px] font-semibold tracking-tightest text-navy"><span class="text-teal">My</span>Kaunsel</span>
      </a>

      <nav class="hidden lg:flex items-center gap-2 text-[15px]">
        <a href="#features" class="nav-glass"><span class="nav-pill" aria-hidden="true"><span class="nav-shine"></span></span><span class="nav-text">Ciri</span></a>
        <a href="#organizations" class="nav-glass"><span class="nav-pill" aria-hidden="true"><span class="nav-shine"></span></span><span class="nav-text">Untuk Organisasi</span></a>
        <a href="#counselors" class="nav-glass"><span class="nav-pill" aria-hidden="true"><span class="nav-shine"></span></span><span class="nav-text">Untuk Kaunselor</span></a>
        <a href="#pricing" class="nav-glass"><span class="nav-pill" aria-hidden="true"><span class="nav-shine"></span></span><span class="nav-text">Harga</span></a>
        <a href="#faq" class="nav-glass"><span class="nav-pill" aria-hidden="true"><span class="nav-shine"></span></span><span class="nav-text">Soalan Lazim</span></a>
      </nav>

      <div class="hidden lg:flex items-center">
        <span class="hdr-line mx-5 block h-5 w-px bg-navy/10"></span>
        @auth
          <a href="{{ route('dashboard') }}" class="hdr-login text-[15px] text-navy/70 hover:text-navy">Papan Pemuka</a>
        @else
          <a href="{{ route('login') }}" class="hdr-login text-[15px] text-navy/70 hover:text-navy">Log Masuk</a>
          <a href="{{ $signup }}"
             class="hdr-signup group ml-5 inline-flex items-center rounded-full border border-navy/15 bg-white px-5 py-2.5 text-sm font-medium text-navy shadow-sm transition-colors duration-300 hover:border-amber hover:bg-amber">
            Daftar
            <span class="ml-0 grid h-0 w-0 shrink-0 place-items-center overflow-hidden rounded-full bg-navy text-cream opacity-0 transition-all duration-300 group-hover:ml-2 group-hover:h-5 group-hover:w-5 group-hover:opacity-100" aria-hidden="true">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
            </span>
          </a>
        @endauth
      </div>

      <button x-on:click="open = !open" class="lg:hidden grid h-10 w-10 place-items-center" aria-label="Menu">
        <span class="relative block h-4 w-6">
          <span class="hdr-bar absolute inset-x-0 top-0 h-[2px] bg-navy transition-transform" x-bind:class="open ? 'translate-y-[7px] rotate-45' : ''"></span>
          <span class="hdr-bar absolute inset-x-0 top-[7px] h-[2px] bg-navy transition-opacity" x-bind:class="open ? 'opacity-0' : ''"></span>
          <span class="hdr-bar absolute inset-x-0 top-[14px] h-[2px] bg-navy transition-transform" x-bind:class="open ? '-translate-y-[7px] -rotate-45' : ''"></span>
        </span>
      </button>
    </div>
  </div>

  <div x-show="open" x-transition.opacity x-cloak class="lg:hidden border-t border-navy/10 bg-cream">
    <div class="flex flex-col gap-1 px-6 py-6 text-lg">
      <a x-on:click="open=false" href="#features" class="py-3">Ciri</a>
      <a x-on:click="open=false" href="#organizations" class="py-3">Untuk Organisasi</a>
      <a x-on:click="open=false" href="#counselors" class="py-3">Untuk Kaunselor</a>
      <a x-on:click="open=false" href="#pricing" class="py-3">Harga</a>
      <a x-on:click="open=false" href="#faq" class="py-3">Soalan Lazim</a>
      <span class="my-3 block h-px w-full bg-navy/10"></span>
      @auth
        <a href="{{ route('dashboard') }}" class="py-3 text-navy/70">Papan Pemuka</a>
      @else
        <a href="{{ route('login') }}" class="py-3 text-navy/70">Log Masuk</a>
        <a href="{{ $signup }}" class="py-3">Daftar</a>
      @endauth
      <a x-on:click="open=false" href="{{ $appUrl }}" class="mt-4 rounded-full bg-navy px-6 py-4 text-center text-base font-medium text-cream">Muat Turun Aplikasi</a>
    </div>
  </div>
</header>
<script>
(function(){
  function start(){
    var hdr = document.getElementById('hdr'), hero = document.getElementById('top');
    if (!hdr || !hero) return;
    if (!window.IntersectionObserver) { hdr.classList.remove('hdr-hero'); return; }
    var sentinel = document.createElement('span');
    sentinel.setAttribute('aria-hidden', 'true');
    sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:40px;pointer-events:none';
    hero.appendChild(sentinel);
    new IntersectionObserver(function(en){
      hdr.classList.toggle('hdr-hero', en[0].isIntersecting);
    }, { threshold: 0 }).observe(sentinel);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
</script>

<!-- 2. HERO — karusel bergelung -->
<style>
  #top { height: 118vh; min-height: 780px; background: #0E2A33; }
  .hero-video { position: absolute; inset: 0; opacity: 0; transition: opacity .8s ease; }
  .hero-video.is-active { opacity: 1; }
  .hero-video video:not([src]) { display: none; }
  .hero-text { animation: heroIn .45s cubic-bezier(.22,1,.36,1) both; }
  @keyframes heroIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

  @media (max-height: 720px) {
    #heroTop { padding-top: 132px; }
    #heroLine { margin-top: 12px; font-size: 14px; }
  }
  @media (max-height: 720px) and (min-width: 640px) {
    #heroTitle { font-size: 40px; margin-top: 16px; }
  }
  .hero-bottom { position: absolute; left: 0; right: 0; top: calc(100vh - 300px); }
  @media (max-width: 767px) { .hero-bottom { top: calc(100vh - 265px); } }
  .hero-track { display: flex; align-items: center; gap: 22px; transform: translate3d(0,0,0);
    transition: transform .72s cubic-bezier(.32,.72,0,1); }
  .hero-track.is-moving { will-change: transform; }
  .hero-track.no-anim { transition: none; }
  .hero-mask { mask-image: linear-gradient(to right, transparent 0%, #000 16%, #000 84%, transparent 100%);
    -webkit-mask-image: linear-gradient(to right, transparent 0%, #000 16%, #000 84%, transparent 100%); }
  .hero-card { position: relative; display: flex; flex-direction: column; overflow: hidden;
    width: 280px; height: 340px; flex: 0 0 280px; border-radius: 16px; background: #FAF8F5;
    box-shadow: 0 30px 64px -28px rgba(0,0,0,.55); text-align: left;
    transform: scale(.78); opacity: .18; backface-visibility: hidden; -webkit-backface-visibility: hidden;
    transition: transform .72s cubic-bezier(.32,.72,0,1), opacity .72s cubic-bezier(.32,.72,0,1); }
  .hero-track.is-moving .hero-card { will-change: transform, opacity; }
  .hero-card[data-pos="-3"], .hero-card[data-pos="3"] { transform: scale(.78); opacity: .18; }
  .hero-card[data-pos="-1"], .hero-card[data-pos="1"] { transform: scale(.88); opacity: .45; }
  .hero-card[data-pos="0"] { transform: scale(1); opacity: 1; }
  .hero-card-image img:not([src]), .hero-card-image img[src=""] { display: none; }
  .hero-card-image { position: relative; display: block; height: 60%; overflow: hidden; background: rgba(14,42,51,.10); }
  .hero-card-bottom { display: flex; flex: 1; flex-direction: column; padding: 16px 18px 18px; }
  .hero-card-child { transition: transform .3s ease-out; }
  .hero-card[data-pos="0"]:hover .hero-card-child { transform: translateX(4px); }
  .hero-card[data-pos="0"]:focus-visible { outline: 2px solid #FAF8F5; outline-offset: 4px; }

  .hero-zone { position: absolute; bottom: 0; top: calc(100vh - 320px); width: 120px; z-index: 20; cursor: pointer; }
  .hero-hint { position: absolute; left: 50%; top: 50%; height: 52px; width: 52px; margin-left: -26px;
    display: grid; place-items: center; border-radius: 9999px; color: #FAF8F5; font-size: 16px;
    background: rgba(250,248,245,.12); border: 1px solid rgba(250,248,245,.25);
    opacity: 0; transform: translateY(-50%) scale(.85);
    transition: opacity .25s ease-out .15s, transform .25s ease-out .15s; }
  @supports (backdrop-filter: blur(10px)) {
    .hero-hint { backdrop-filter: blur(10px) saturate(140%); -webkit-backdrop-filter: blur(10px) saturate(140%); }
  }
  .hero-zone:hover .hero-hint { opacity: 1; transform: translateY(-50%) scale(1); }

  .hero-dot { height: 7px; width: 7px; border-radius: 9999px; background: rgba(250,248,245,.28);
    transition: width .4s cubic-bezier(.22,1,.36,1), background-color .3s ease-out; }
  .hero-dot.is-active { width: 24px; background: #2E9AA8; }
  #heroBar { height: 2px; background: #2E9AA8; width: 0; }

  @media (max-width: 767px) {
    #top { height: 110vh; min-height: 680px; }
    .hero-card { width: 240px; flex-basis: 240px; height: 300px; }
    .hero-zone { display: none; }
  }
  @media (prefers-reduced-motion: reduce) {
    .hero-text { animation: heroFade .4s ease-out both; }
    @keyframes heroFade { from { opacity: 0; } to { opacity: 1; } }
    .hero-track { transition: none; }
    .hero-card { transition: opacity .3s ease-out; }
    .hero-hint, .hero-dot, .hero-card-child { transition: opacity .25s ease-out; }
  }
</style>
<section id="top" class="relative overflow-hidden">

  @foreach ([['#123A45', 'VIDEO: individu menggunakan aplikasi'], ['#0E2A33', 'VIDEO: pasukan unit kaunseling'], ['#15414A', 'VIDEO: kaunselor berdaftar bekerja']] as $n => [$bg, $caption])
    <div class="hero-video {{ $n === 0 ? 'is-active' : '' }}" data-video="{{ $n }}" style="background:{{ $bg }}">
      <span class="pointer-events-none absolute inset-x-0 bottom-4 px-8 text-center text-[11px] uppercase tracking-[0.2em] text-cream/25">{{ $caption }}</span>
      <video data-slot="hero-videoeo-{{ $n + 1 }}" autoplay muted loop playsinline preload="metadata" class="absolute inset-0 h-full w-full object-cover"></video>
    </div>
  @endforeach
  <span class="pointer-events-none absolute inset-0" style="background:linear-gradient(to bottom, rgba(14,42,51,.45) 0%, rgba(14,42,51,.22) 18%, rgba(14,42,51,.18) 45%, rgba(14,42,51,.72) 70%, rgba(14,42,51,.95) 88%, #0E2A33 100%)"></span>

  <div class="relative z-10 h-full">
    <div id="heroTop" class="flex flex-col items-center px-6 pt-[max(15vh,150px)] text-center">
      <p class="text-[12px] uppercase tracking-[0.22em]" style="color:#4FB9C6">Platform kaunseling Malaysia</p>
      <h1 id="heroTitle" class="hero-text mt-6 max-w-[20ch] text-[34px] font-semibold leading-[1.05] tracking-tightest sm:text-[56px] lg:text-[68px]" style="color:#FAF8F5;animation-delay:.1s;text-wrap:balance">Mula dengan satu perbualan.</h1>
      <p id="heroLine" class="hero-text mt-5 max-w-[70ch] text-[15px] leading-relaxed lg:text-[17px]" style="color:rgba(250,248,245,.7);animation-delay:.16s">Cari kaunselor berdaftar dan tempah sesi terus dari telefon anda.</p>
    </div>

    <div class="hero-bottom">
      <div class="hero-mask overflow-hidden px-6">
        <div id="heroTrack" class="hero-track justify-start">
          @foreach ([-2, -1, 0, 1, 2] as $pos)
            <a href="#" class="hero-card" data-pos="{{ $pos }}">
              <span class="hero-card-image">
                <img data-slot="" alt="" class="h-full w-full object-cover">
                <span class="hero-card-label pointer-events-none absolute inset-0 grid place-items-center text-[10px] uppercase tracking-[0.16em] text-navy/30"></span>
              </span>
              <span class="hero-card-bottom">
                <span class="hero-card-title block text-[15px] font-semibold tracking-tight text-navy"></span>
                <span class="hero-card-text mt-1.5 block text-[12.5px] leading-snug text-navy/55"></span>
                <span class="mt-auto flex items-center gap-1.5 pt-3 text-[12.5px] font-medium text-teal">
                  <span class="hero-card-link"></span>
                  <span class="hero-card-child" aria-hidden="true">→</span>
                </span>
              </span>
            </a>
          @endforeach
        </div>
      </div>
      <div class="mt-7 flex flex-col items-center gap-3">
        <div class="flex items-center gap-2">
          <button type="button" class="hero-dot is-active" data-dot="0" aria-label="Saya mencari sokongan"></button>
          <button type="button" class="hero-dot" data-dot="1" aria-label="Kami sebuah organisasi"></button>
          <button type="button" class="hero-dot" data-dot="2" aria-label="Saya seorang kaunselor"></button>
        </div>
        <div class="h-[2px] w-[120px] overflow-hidden rounded-full" style="background:rgba(250,248,245,.14)">
          <div id="heroBar"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="hero-zone" id="heroZonKiri" style="left:0" role="button" tabindex="-1" aria-label="Kad sebelumnya">
    <span class="hero-hint" aria-hidden="true">←</span>
  </div>
  <div class="hero-zone" id="heroZonKanan" style="right:0" role="button" tabindex="-1" aria-label="Kad seterusnya">
    <span class="hero-hint" aria-hidden="true">→</span>
  </div>
</section>
<script>
(function(){
  var SLIDE_DURATION = 6000;
  var RESUME_DELAY = 3000;
  // Where each card leads.
  var HREF = { individual: @json($appUrl), organization: @json($signup), counselor: @json($signup) };
  var dataMy = [{"id":"individual","title":"Mula dengan satu perbualan.","line":"Cari kaunselor berdaftar dan tempah sesi terus dari telefon anda.","short":"Saya mencari sokongan","detail":"Chatbot, carian kaunselor, dan tempahan dalam satu aplikasi.","link":"Muat turun aplikasi","label":"GAMBAR: individu"},{"id":"organization","title":"Satu portal untuk seluruh unit kaunseling.","line":"Universiti dan klinik menguruskan kaunselor, jadual dan tempahan di satu tempat.","short":"Kami sebuah organisasi","detail":"Portal pengurusan untuk universiti dan klinik.","link":"Daftar organisasi","label":"GAMBAR: organisasi"},{"id":"counselor","title":"Praktis anda, jadual anda.","line":"Terima tempahan tanpa perlu membina sistem anda sendiri.","short":"Saya seorang kaunselor","detail":"Planner, slot terbuka, dan tempahan automatik.","link":"Sertai sebagai kaunselor","label":"GAMBAR: kaunselor"}];
  var dataEn = [{"id":"individual","title":"Start with one conversation.","line":"Find a registered counselor and book sessions right from your phone.","short":"I'm looking for support","detail":"Chatbot, counselor search, and booking in one app.","link":"Download the app","label":"IMAGE: individual"},{"id":"organization","title":"One portal for your whole counseling unit.","line":"Universities and clinics manage counselors, schedules, and bookings in one place.","short":"We're an organization","detail":"A management portal for universities and clinics.","link":"Register your organization","label":"IMAGE: organization"},{"id":"counselor","title":"Your practice, your schedule.","line":"Accept bookings without building your own system.","short":"I'm a counselor","detail":"Planner, open slots, and automatic bookings.","link":"Join as a counselor","label":"IMAGE: counselor"}];
  var data = (localStorage.getItem('mk_lang') === 'en') ? dataEn : dataMy;
  var N = data.length;

  function init(){
    var hero = document.getElementById('top');
    if (!hero) return;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var vids = [].slice.call(hero.querySelectorAll('.hero-video'));
    var track = document.getElementById('heroTrack');
    var slots = [].slice.call(track.querySelectorAll('.hero-card'));
    var dots = [].slice.call(hero.querySelectorAll('.hero-dot'));
    var bar = document.getElementById('heroBar');
    var title = document.getElementById('heroTitle');
    var line = document.getElementById('heroLine');
    var active = 0, animating = false, guardT = null, paused = false, resumeT = null, t0 = 0, elapsed = 0;

    function fill(){
      slots.forEach(function(el){
        var p = +el.getAttribute('data-pos');
        var d = data[((active + p) % N + N) % N];
        if (Math.abs(p) > 2) { el.setAttribute('data-pos', p > 0 ? '3' : '-3'); }
        el.setAttribute('data-card-id', d.id);
        el.setAttribute('href', HREF[d.id] || '#');
        el.setAttribute('aria-label', d.short);
        el.setAttribute('tabindex', p === 0 ? '0' : '-1');
        el.querySelector('img').setAttribute('data-slot', 'card-' + d.id);
        el.querySelector('.hero-card-label').textContent = d.label;
        el.querySelector('.hero-card-title').textContent = d.short;
        el.querySelector('.hero-card-text').textContent = d.detail;
        el.querySelector('.hero-card-link').textContent = d.link;
      });
    }
    function centre(){
      var mid = slots[2];
      var pb = track.parentElement.getBoundingClientRect();
      var mb = mid.getBoundingClientRect();
      var cur = 0;
      try { cur = new DOMMatrixReadOnly(getComputedStyle(track).transform).m41; } catch(e){}
      var off = cur + ((pb.left + pb.width / 2) - (mb.left + mb.width / 2));
      track.style.transition = 'none';
      track.style.transform = 'translate3d(' + off + 'px,0,0)';
      void track.offsetHeight;
      track.style.transition = '';
      return off;
    }
    function reanim(el, text, delay){
      el.textContent = text;
      el.classList.remove('hero-text');
      void el.offsetWidth;
      el.style.animationDelay = delay;
      el.classList.add('hero-text');
    }

    var base = 0;
    var topPart = document.getElementById('heroTop');
    var bottomPart = hero.querySelector('.hero-bottom');
    function spacing(){
      if (!topPart || !bottomPart) return;
      var min = topPart.getBoundingClientRect().bottom - hero.getBoundingClientRect().top + 72;
      var dflt = window.innerHeight - (window.innerWidth < 768 ? 265 : 300);
      bottomPart.style.top = Math.max(min, dflt) + 'px';
    }
    function layout(){ spacing(); base = centre(); }
    function paint(){
      vids.forEach(function(el, n){
        el.classList.toggle('is-active', n === active);
        var v = el.querySelector('video');
        if (!v) return;
        try { if (n === active && !reduce) v.play(); else v.pause(); } catch(e){}
      });
      dots.forEach(function(el, n){ el.classList.toggle('is-active', n === active); });
      reanim(title, data[active].title, '.1s');
      reanim(line, data[active].line, '.16s');
    }

    function settle(){
      if (!animating) return;
      animating = false;
      slots.forEach(function(el, n){
        el.style.transition = 'none';
        el.setAttribute('data-pos', String(n - 2));
      });
      fill();
      layout();
      void track.offsetHeight;
      slots.forEach(function(el){ el.style.transition = ''; });
      track.classList.remove('is-moving');
    }
    function go(dir){
      if (animating) return;
      var step = slots[1].offsetLeft - slots[0].offsetLeft;
      active = ((active + dir) % N + N) % N;
      restart();
      if (reduce) { fill(); layout(); paint(); return; }
      animating = true;
      track.classList.add('is-moving');
      slots.forEach(function(el){
        el.setAttribute('data-pos', String(+el.getAttribute('data-pos') - dir));
      });
      track.style.transform = 'translate3d(' + (base - dir * step) + 'px,0,0)';
      paint();
      clearTimeout(guardT);
      guardT = setTimeout(settle, 760);
    }
    function jump(i){
      if (i === active || animating) return;
      var fwd = ((i - active) % N + N) % N;
      go(fwd === 1 ? 1 : (fwd === N - 1 ? -1 : 1));
    }

    // auto-maju
    function restart(){ elapsed = 0; t0 = performance.now(); }
    function pause(){ paused = true; if (resumeT) { clearTimeout(resumeT); resumeT = null; } }
    function resumeSoon(){
      if (resumeT) clearTimeout(resumeT);
      resumeT = setTimeout(function(){ paused = false; restart(); }, RESUME_DELAY);
    }
    function tick(now){
      if (!reduce) {
        if (paused || document.hidden) { t0 = now; }
        else {
          elapsed += now - t0; t0 = now;
          bar.style.width = Math.min(100, elapsed / SLIDE_DURATION * 100) + '%';
          if (elapsed >= SLIDE_DURATION) { go(1); }
        }
      }
      requestAnimationFrame(tick);
    }

    track.addEventListener('transitionend', function(e){
      if (e.target === track && e.propertyName === 'transform') { clearTimeout(guardT); settle(); }
    });
    fill(); layout(); restart();
    window.__mkSetHeroLang = function(lang){
      data = (lang === 'en') ? dataEn : dataMy;
      fill(); layout(); paint();
    };
    window.addEventListener('resize', function(){ layout(); });
    if (!reduce) { t0 = performance.now(); requestAnimationFrame(tick); }

    track.addEventListener('mouseenter', pause);
    track.addEventListener('mouseleave', resumeSoon);

    ['heroZonKiri','heroZonKanan'].forEach(function(id, n){
      var el = document.getElementById(id);
      var dir = n === 0 ? -1 : 1;
      el.addEventListener('click', function(){ pause(); go(dir); resumeSoon(); });
    });
    dots.forEach(function(d, n){ d.addEventListener('click', function(){ pause(); jump(n); resumeSoon(); }); });

    var nampak = true;
    if (window.IntersectionObserver) {
      new IntersectionObserver(function(en){ nampak = en[0].isIntersecting; }, { threshold: .25 }).observe(hero);
    }
    document.addEventListener('keydown', function(e){
      if (!nampak) return;
      if (e.key === 'ArrowLeft') { pause(); go(-1); resumeSoon(); }
      else if (e.key === 'ArrowRight') { pause(); go(1); resumeSoon(); }
    });

    var x0 = null;
    hero.addEventListener('touchstart', function(e){ x0 = e.touches[0].clientX; pause(); }, { passive: true });
    hero.addEventListener('touchend', function(e){
      if (x0 === null) { resumeSoon(); return; }
      var dx = e.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 45) go(dx < 0 ? 1 : -1);
      resumeSoon();
    }, { passive: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>

<!-- 3. JALUR KEPERCAYAAN -->
<style>
  .trust-item{--aksen:#0F6B7D}
  .trust-box{width:52px;height:52px;border-radius:14px;background:color-mix(in srgb, var(--aksen) 10%, transparent);border:1px solid color-mix(in srgb, var(--aksen) 15%, transparent);color:var(--aksen);
    transition:background-color .35s cubic-bezier(.22,1,.36,1),border-color .35s cubic-bezier(.22,1,.36,1),color .35s cubic-bezier(.22,1,.36,1),transform .35s cubic-bezier(.22,1,.36,1)}
  .trust-icon path,.trust-icon rect,.trust-icon ellipse{stroke-dasharray:var(--len,0);stroke-dashoffset:0}
  .trust-title{transition:color .3s ease-out}
  @media (min-width:640px){ .trust-item:hover .trust-title{color:var(--aksen)} }
  .trust-divider{width:1px;height:48px;background:rgba(14,42,51,.10);align-self:center}
  @media (min-width:640px){
    .trust-item:hover .trust-box{background:var(--aksen);border-color:var(--aksen);color:#FAF8F5;transform:translateY(-3px) scale(1.04)}
    .trust-item:hover .trust-icon path,.trust-item:hover .trust-icon rect,.trust-item:hover .trust-icon ellipse{animation:trust-draw .6s ease-out}
  }
  @keyframes trust-draw{from{stroke-dashoffset:var(--len,0)}to{stroke-dashoffset:0}}
  @media (prefers-reduced-motion:reduce){
    .trust-box{transition:background-color .3s,border-color .3s,color .3s}
    .trust-item:hover .trust-box{transform:none}
    .trust-item:hover .trust-icon path,.trust-item:hover .trust-icon rect,.trust-item:hover .trust-icon ellipse{animation:none}
  }
</style>
<section class="border-y border-navy/10" style="background:rgba(14,42,51,.035)">
  <div class="mx-auto max-w-7xl px-6 py-8 lg:px-10 lg:py-9">
    <div class="flex flex-col divide-y divide-navy/10 sm:flex-row sm:items-center sm:justify-between sm:gap-8 sm:divide-y-0 [&>.trust-item]:py-5 sm:[&>.trust-item]:py-0">
      @php
        $trust = [
            ['#0F6B7D', 'Kaunselor disahkan', 'Setiap profil disemak terhadap pendaftaran Lembaga Kaunselor Malaysia', '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>'],
            ['#6B5B95', 'Perbualan peribadi', 'Apa yang anda tulis tidak dikongsi dengan kaunselor atau organisasi', '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
            ['#D98F4A', 'Mematuhi PDPA', 'Data dikumpul dan disimpan mengikut Akta Perlindungan Data Peribadi', '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>'],
        ];
      @endphp
      @foreach ($trust as $n => [$accent, $title, $text, $icon])
        <div class="trust-item group reveal flex items-start gap-4 sm:items-center" style="--aksen: {{ $accent }}">
          <span class="trust-box grid shrink-0 place-items-center" aria-hidden="true">
            <svg class="trust-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
          </span>
          <div>
            <p class="trust-title text-[15px] font-semibold tracking-tight text-navy">{{ $title }}</p>
            <p class="mt-1.5 max-w-[34ch] text-[13.5px] leading-relaxed text-navy/55">{{ $text }}</p>
          </div>
        </div>
        @if (! $loop->last)
          <span class="trust-divider hidden shrink-0 sm:block" aria-hidden="true"></span>
        @endif
      @endforeach
    </div>
  </div>
</section>
<script>
  document.querySelectorAll('.trust-icon').forEach(function(svg){
    svg.querySelectorAll('path,rect,ellipse,circle,line').forEach(function(p){
      try{ p.style.setProperty('--len', p.getTotalLength()); }catch(e){}
    });
  });
</script>

<!-- 4. MASALAH -->
<section class="relative overflow-hidden bg-navy grain text-cream">
  <div class="mx-auto max-w-7xl px-6 pb-20 pt-16 lg:px-10 lg:pb-24 lg:pt-20">
    <h2 class="reveal max-w-3xl text-[34px] leading-[1.1] tracking-tightest font-semibold sm:text-5xl lg:text-[62px]">
      Bantuan itu ada. Jalan ke arahnya yang selalu tersekat.
    </h2>
    <div class="mt-10 grid gap-px overflow-hidden rounded-2xl bg-cream/15 sm:grid-cols-3 lg:mt-14">
      <div class="reveal bg-navy p-8 lg:p-10">
        <p class="text-[13px] uppercase tracking-[0.16em] text-amber">Tidak tahu mula</p>
        <p class="mt-5 text-lg leading-relaxed text-cream/75">Ramai tidak pasti sama ada apa yang dirasai cukup "serius" untuk dibawa kepada kaunselor.</p>
      </div>
      <div class="reveal bg-navy p-8 lg:p-10">
        <p class="text-[13px] uppercase tracking-[0.16em] text-amber">Sukar cari</p>
        <p class="mt-5 text-lg leading-relaxed text-cream/75">Maklumat kaunselor bertaburan — nombor telefon, media sosial, cadangan mulut ke mulut.</p>
      </div>
      <div class="reveal bg-navy p-8 lg:p-10">
        <p class="text-[13px] uppercase tracking-[0.16em] text-amber">Risau privasi</p>
        <p class="mt-5 text-lg leading-relaxed text-cream/75">Kebimbangan tentang siapa yang akan tahu sering menghentikan langkah pertama.</p>
      </div>
    </div>
  </div>
</section>

<!-- 6. SATU PLATFORM — accordion -->
<section id="features" class="border-t border-navy/10 bg-cream py-20 lg:py-24" x-data="{ active: 0 }">
  <div class="mx-auto max-w-7xl px-6 lg:px-10">
    <div class="max-w-3xl">
      <h2 class="reveal text-[34px] leading-[1.08] tracking-tightest font-semibold sm:text-5xl lg:text-[58px]">Satu platform, dari perbualan pertama sehingga sesi selesai</h2>
      <p class="reveal mt-6 max-w-xl text-[17px] leading-relaxed text-navy/60">Setiap peringkat direka supaya langkah seterusnya sentiasa jelas — untuk pengguna, kaunselor, dan organisasi.</p>
    </div>

    <div class="mt-14 grid gap-10 lg:mt-16 lg:grid-cols-5 lg:gap-16">
      <div class="reveal hidden lg:col-span-2 lg:block">
        <div class="sticky top-28 relative aspect-[4/5] overflow-hidden rounded-[14px]">
          @foreach ($steps as $n => $step)
            <div class="step-visual absolute inset-0" x-bind:class="active === {{ $n }} ? 'is-shown' : ''" style="background:{{ $step[4] }}">
              <span class="absolute inset-0 grid place-items-center px-6 text-center text-[11px] uppercase tracking-[0.16em] text-navy/35">{{ $step[3] }}</span>
              <img data-slot="step-{{ $n + 1 }}" alt="" class="step-img relative h-full w-full object-cover">
            </div>
          @endforeach
        </div>
      </div>

      <div class="reveal lg:col-span-3">
        <div class="border-t border-navy/10"></div>
        @foreach ($steps as $n => [$title, $text, $link, $caption, $tint])
          <div class="step-item border-b border-navy/10" data-i="{{ $n }}">
            <button type="button" class="step-btn flex w-full items-center justify-between gap-6 py-6 text-left lg:py-7"
                    x-on:mouseenter="if (window.matchMedia('(min-width:1024px)').matches) active = {{ $n }}"
                    x-on:click="active = {{ $n }}"
                    x-on:focus="active = {{ $n }}"
                    x-bind:aria-expanded="active === {{ $n }}">
              <span class="step-title text-[32px] leading-[1.05] tracking-tightest font-semibold sm:text-[40px] lg:text-[48px]"
                    x-bind:class="active === {{ $n }} ? 'text-navy' : 'text-navy/25'">{{ $title }}</span>
              <span class="shrink-0 text-navy" x-show="active === {{ $n }}" x-transition.opacity.duration.300ms><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></svg></span>
            </button>
            <div class="step-panel" x-bind:class="active === {{ $n }} ? 'is-open' : ''">
              <div class="overflow-hidden">
                <div class="lg:hidden">
                  <div class="relative mb-5 aspect-[4/5] overflow-hidden rounded-[14px]" style="background:{{ $tint }}">
                    <span class="absolute inset-0 grid place-items-center px-6 text-center text-[11px] uppercase tracking-[0.16em] text-navy/35">{{ $caption }}</span>
                    <img data-slot="step-{{ $n + 1 }}-m" alt="" class="step-img relative h-full w-full object-cover">
                  </div>
                </div>
                <p class="max-w-xl text-[16px] leading-relaxed text-navy/65 lg:text-[17px]">{{ $text }}</p>
                <a href="#faq" class="mt-5 inline-flex items-center gap-2 pb-8 text-[15px] font-medium text-navy underline decoration-navy/25 underline-offset-4 hover:decoration-navy">
                  {{ $link }}
                  {!! $tick !!}
                </a>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>

<!-- 7. DARK — pengesahan kelayakan -->
<style>
  .pk-card { border-radius: 24px; background: linear-gradient(135deg, #0B3A42 0%, #0E2A33 55%, #08202A 100%);
    box-shadow: 0 24px 60px -20px rgba(14,42,51,.28); opacity: 0; transform: translateY(24px);
    transition: opacity .8s ease-out, transform .8s ease-out; }
  .pk-card.pk-in { opacity: 1; transform: none; }
  .pk-badge { height: 44px; width: 44px; border: 2px solid #0E2A33; border-radius: 9999px;
    display: grid; place-items: center; opacity: 0; transform: scale(.8);
    transition: opacity .5s ease-out, transform .5s cubic-bezier(.32,.72,0,1); }
  .pk-card.pk-in .pk-badge { opacity: 1; transform: none; }
  .pk-card.pk-in .pk-badge:nth-child(2) { transition-delay: .08s; }
  .pk-card.pk-in .pk-badge:nth-child(3) { transition-delay: .16s; }
  .pk-text { opacity: 0; transform: translateY(16px); transition: opacity .7s ease-out .2s, transform .7s ease-out .2s; }
  .pk-card.pk-in .pk-text { opacity: 1; transform: none; }
  .pk-media { position: relative; opacity: 0; transform: scale(1.06);
    transition: opacity 1.2s ease-out, transform 1.2s ease-out; }
  .pk-card.pk-in .pk-media { opacity: 1; transform: scale(1); }
  .pk-media-inner { will-change: transform; }
  .frameless-media, .pk-ph { display: block; width: 100%; height: auto; object-fit: cover;
    border-radius: 20px; box-shadow: 0 30px 70px -40px rgba(0,0,0,.7); }
  @supports (mask-composite: intersect) or (-webkit-mask-composite: source-in) {
    .frameless-media, .pk-ph { border-radius: 0; box-shadow: none;
      -webkit-mask-image: linear-gradient(to right, transparent 0%, black 14%, black 86%, transparent 100%),
                          linear-gradient(to bottom, transparent 0%, black 12%, black 88%, transparent 100%);
      mask-image: linear-gradient(to right, transparent 0%, black 14%, black 86%, transparent 100%),
                  linear-gradient(to bottom, transparent 0%, black 12%, black 88%, transparent 100%);
      -webkit-mask-composite: source-in; mask-composite: intersect; }
    @media (max-width: 767px) {
      .frameless-media, .pk-ph {
        -webkit-mask-image: linear-gradient(to right, transparent 0%, black 7%, black 93%, transparent 100%),
                            linear-gradient(to bottom, transparent 0%, black 6%, black 94%, transparent 100%);
        mask-image: linear-gradient(to right, transparent 0%, black 7%, black 93%, transparent 100%),
                    linear-gradient(to bottom, transparent 0%, black 6%, black 94%, transparent 100%); }
    }
  }
  .frameless-media:not([src]) { display: none; }
  .pk-ph { aspect-ratio: 4 / 3; max-height: 60vh; background: linear-gradient(150deg, #1B5A66 0%, #14414C 60%, #103440 100%);
    display: grid; place-items: center; }
  @media (min-width: 1024px) { .pk-ph { aspect-ratio: 4 / 5; max-height: none; } }
  @media (prefers-reduced-motion: reduce) {
    .pk-card, .pk-badge, .pk-text, .pk-media { transition: opacity .4s ease-out; transform: none !important; }
  }
</style>
<section class="bg-cream py-14 lg:py-20">
  <div class="mx-auto max-w-7xl px-6 lg:px-10">
    <div id="pkCard" class="pk-card relative mx-auto overflow-hidden px-8 py-10 text-cream lg:px-16 lg:py-16">
      <div class="grid items-stretch gap-12 lg:grid-cols-[55%_45%] lg:gap-10">
        <div class="flex flex-col justify-between">
          <div class="flex items-center">
            <span class="pk-badge" style="background:#0F6B7D">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
            </span>
            <span class="pk-badge -ml-3" style="background:#FAF8F5">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0E2A33" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/></svg>
            </span>
            <span class="pk-badge -ml-3" style="background:#D98F4A">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0E2A33" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
            </span>
          </div>

          <div class="pk-text mt-16 lg:mt-24">
            <p class="text-[13px] uppercase tracking-[0.18em] text-amber">Pengesahan kelayakan</p>
            <h2 class="mt-6 max-w-[22ch] text-[34px] font-semibold leading-[1.08] tracking-tightest sm:text-[44px] lg:text-[54px]">Setiap kaunselor disahkan dahulu.</h2>
            <p class="mt-7 max-w-xl text-[16px] leading-relaxed text-cream/65 lg:text-[17px]">
              Kami semak setiap kaunselor dengan Lembaga Kaunselor Malaysia. Yang tidak lulus semakan tidak akan muncul dalam aplikasi.
            </p>
            <div class="mt-9 flex flex-wrap gap-3 text-sm">
              <span class="rounded-full border border-cream/20 px-4 py-2 text-cream/75">Nombor pendaftaran disemak</span>
              <span class="rounded-full border border-cream/20 px-4 py-2 text-cream/75">Lesen amalan sah</span>
              <span class="rounded-full border border-cream/20 px-4 py-2 text-cream/75">Semakan berkala</span>
            </div>
            <a href="#features" class="mt-8 inline-flex items-center gap-2 border-b border-cream/40 pb-1 text-[15px] text-cream transition-colors duration-300 hover:border-cream">Ketahui lebih lanjut tentang proses pengesahan <span aria-hidden="true">→</span></a>
          </div>
        </div>

        <div class="pk-media flex items-center lg:-mr-10 lg:-my-6">
          <div id="pkMedia" class="pk-media-inner w-full lg:w-[112%]">
            <video data-slot="pengesahan-video" autoplay muted loop playsinline preload="metadata" class="frameless-media"></video>
            <div class="pk-ph">
              <span class="px-8 text-center text-[11px] uppercase tracking-[0.2em] text-cream/30">VIDEO: proses pengesahan kaunselor</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<script>
(function(){
  function start(){
    var card = document.getElementById('pkCard'), media = document.getElementById('pkMedia');
    if (!card) return;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (window.IntersectionObserver) {
      new IntersectionObserver(function(en, obs){
        if (en[0].isIntersecting) { card.classList.add('pk-in'); obs.disconnect(); }
      }, { threshold: .2 }).observe(card);
    } else { card.classList.add('pk-in'); }

    var v = card.querySelector('video');
    if (v && reduce) { try { v.pause(); } catch(e){} }
    if (reduce || !media) return;

    var pending = false;
    window.addEventListener('scroll', function(){
      if (pending) return;
      pending = true;
      requestAnimationFrame(function(){
        pending = false;
        var r = card.getBoundingClientRect();
        var p = (window.innerHeight / 2 - (r.top + r.height / 2)) / window.innerHeight;
        media.style.transform = 'translate3d(0,' + Math.max(-20, Math.min(20, p * 40)).toFixed(2) + 'px,0)';
      });
    }, { passive: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
</script>

<!-- 9. SIAPA BOLEH TEMPAH SIAPA -->
<section class="bg-cream pb-20 lg:pb-24">
  <div class="mx-auto max-w-7xl px-6 lg:px-10">
    <h2 class="reveal max-w-3xl text-[34px] leading-[1.08] tracking-tightest font-semibold sm:text-5xl lg:text-[58px]">Akses bergantung pada cara anda menyertai</h2>
    <p class="reveal mt-6 max-w-2xl text-[17px] leading-relaxed text-navy/60">Organisasi menyediakan kaunselor untuk ahli mereka sendiri. Kaunselor bebas dan klinik terbuka kepada semua pengguna platform.</p>
    <div class="mt-12 grid gap-6 lg:grid-cols-2">
      <div class="reveal rounded-3xl border border-navy/10 bg-white p-9 lg:p-11" style="border-top:3px solid #0F6B7D">
        <p class="text-[13px] uppercase tracking-[0.16em] text-teal">Ahli organisasi</p>
        <p class="mt-5 text-[17px] leading-relaxed text-navy/70">Anda menyertai melalui domain e-mel institusi atau kod jemputan.</p>
        <p class="mt-8 text-[13px] uppercase tracking-[0.14em] text-navy/40">Boleh tempah</p>
        <ul class="mt-4 space-y-3 text-[16px]">
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Kaunselor organisasi anda</span></li>
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Kaunselor bebas dan klinik</span></li>
        </ul>
      </div>
      <div class="reveal rounded-3xl border border-navy/10 bg-white p-9 lg:p-11" style="border-top:3px solid #D98F4A">
        <p class="text-[13px] uppercase tracking-[0.16em]" style="color:#B5722F">Pengguna awam</p>
        <p class="mt-5 text-[17px] leading-relaxed text-navy/70">Anda mendaftar sendiri dengan e-mel peribadi.</p>
        <p class="mt-8 text-[13px] uppercase tracking-[0.14em] text-navy/40">Boleh tempah</p>
        <ul class="mt-4 space-y-3 text-[16px]">
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-amber"></span><span>Kaunselor bebas dan klinik</span></li>
        </ul>
        <p class="mt-7 border-t border-navy/10 pt-5 text-[14px] leading-relaxed text-navy/50">Kaunselor organisasi hanya terbuka kepada ahli organisasi tersebut.</p>
      </div>
    </div>
  </div>
</section>

<!-- 10. HARGA -->
<section id="pricing" class="border-t border-navy/10 bg-cream py-20 lg:py-24">
  <div class="mx-auto max-w-7xl px-6 lg:px-10">
    <h2 class="reveal max-w-3xl text-[34px] leading-[1.08] tracking-tightest font-semibold sm:text-5xl lg:text-[58px]">Harga yang jelas untuk setiap laluan</h2>
    <div class="mt-12 grid items-start gap-6 lg:grid-cols-3">
      <div class="reveal rounded-3xl border border-navy/10 bg-white p-9">
        <p class="text-xl font-semibold tracking-tight">Individu</p>
        <p class="mt-3 text-[15px] text-navy/55">Percuma untuk dimuat turun</p>
        <ul class="mt-8 space-y-3.5 border-t border-navy/10 pt-7 text-[16px]">
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Chatbot sokongan tanpa had</span></li>
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Cari kaunselor berdaftar</span></li>
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Bayaran hanya untuk sesi yang ditempah</span></li>
        </ul>
        <a href="{{ $appUrl }}" class="mt-9 inline-flex w-full items-center justify-center rounded-full border border-navy/25 px-6 py-3.5 text-[15px] font-medium text-navy transition-colors duration-300 hover:border-navy hover:bg-navy/5">Muat turun aplikasi</a>
      </div>
      <div class="reveal rounded-3xl bg-navy p-9 text-cream lg:-mt-4 lg:p-11" style="box-shadow:0 30px 60px -24px rgba(14,42,51,.45)">
        <p class="text-xl font-semibold tracking-tight">Organisasi</p>
        <p class="mt-3 text-[15px] text-cream/60">Langganan tahunan</p>
        <ul class="mt-8 space-y-3.5 border-t border-cream/15 pt-7 text-[16px] text-cream/85">
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-amber"></span><span>Kaunselor tanpa had dalam portal</span></li>
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-amber"></span><span>Pengurusan ahli dan ketersediaan</span></li>
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-amber"></span><span>Rekod tempahan dan laporan</span></li>
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-amber"></span><span>Harga mengikut saiz keahlian</span></li>
        </ul>
        <a href="{{ route('help') }}" class="mt-9 inline-flex w-full items-center justify-center rounded-full bg-amber px-6 py-3.5 text-[15px] font-medium text-navy transition-colors duration-300 hover:bg-cream">Hubungi kami</a>
      </div>
      <div class="reveal rounded-3xl border border-navy/10 bg-white p-9">
        <p class="text-xl font-semibold tracking-tight">Kaunselor</p>
        <p class="mt-3 text-[15px] text-navy/55">Percuma untuk menyertai</p>
        <ul class="mt-8 space-y-3.5 border-t border-navy/10 pt-7 text-[16px]">
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Pengesahan kelayakan disediakan</span></li>
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Planner dan pengurusan sesi</span></li>
          <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Komisen kecil setiap sesi yang selesai</span></li>
        </ul>
        <a href="#counselors" class="mt-9 inline-flex w-full items-center justify-center rounded-full border border-navy/25 px-6 py-3.5 text-[15px] font-medium text-navy transition-colors duration-300 hover:border-navy hover:bg-navy/5">Sertai sebagai kaunselor</a>
      </div>
    </div>
  </div>
</section>

<!-- 11. UNTUK ORGANISASI -->
<section id="organizations" class="bg-cream pb-20 lg:pb-24">
  <div class="mx-auto max-w-7xl px-6 lg:px-10">
    <div class="reveal overflow-hidden rounded-3xl border border-navy/10 bg-white">
      <div class="grid lg:grid-cols-2">
        <div class="p-9 lg:p-12">
          <p class="text-[13px] uppercase tracking-[0.18em] text-teal">Untuk organisasi</p>
          <h2 class="mt-6 text-[32px] leading-[1.08] tracking-tightest font-semibold lg:text-[48px]">Untuk universiti dan klinik</h2>
          <p class="mt-6 max-w-lg text-[17px] leading-relaxed text-navy/65">Bawa perkhidmatan kaunseling anda yang sudah ada ke dalam satu sistem tempahan dan pengurusan.</p>
          <div class="mt-10 flex flex-col gap-3 sm:flex-row">
            <a href="{{ $signup }}" class="inline-flex items-center justify-center rounded-full bg-amber px-8 py-4 text-[15px] font-medium text-navy transition-colors duration-300 hover:bg-navy hover:text-cream">Daftar Organisasi Anda</a>
            <a href="#features" class="inline-flex items-center justify-center rounded-full border border-navy/25 px-8 py-4 text-[15px] font-medium text-navy transition-colors duration-300 hover:border-navy hover:bg-navy/5">Ketahui lebih lanjut</a>
          </div>
        </div>
        <div class="border-t border-navy/10 bg-cream p-9 lg:border-l lg:border-t-0 lg:p-12">
          <ul class="divide-y divide-navy/10 text-[16px]">
            <li class="flex gap-4 py-5"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Senaraikan kaunselor organisasi dan uruskan ketersediaan mereka dari satu tempat.</span></li>
            <li class="flex gap-4 py-5"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Terima tempahan pelajar atau pesakit tanpa urusan melalui telefon dan e-mel.</span></li>
            <li class="flex gap-4 py-5"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Semak rekod sesi dan jadual untuk perancangan sumber.</span></li>
            <li class="flex gap-4 py-5"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Kaunselor yang didaftarkan melalui proses pengesahan kelayakan yang sama.</span></li>
            <li class="flex gap-4 py-5"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Akses terhad kepada maklumat tempahan sahaja — kandungan chat kekal peribadi.</span></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 12. UNTUK KAUNSELOR -->
<section id="counselors" class="bg-cream pb-20 lg:pb-24">
  <div class="mx-auto max-w-7xl px-6 lg:px-10">
    <div class="reveal overflow-hidden rounded-3xl border border-navy/10 bg-white">
      <div class="grid lg:grid-cols-2">
        <div class="relative order-2 min-h-[280px] overflow-hidden border-t border-navy/10 lg:order-1 lg:border-r lg:border-t-0" style="background:rgba(217,143,74,.12)">
          <span class="absolute inset-0 grid place-items-center px-6 text-center text-[11px] uppercase tracking-[0.16em] text-navy/35">GAMBAR: kaunselor berdaftar</span>
          <img data-slot="untuk-kaunselor" alt="" class="step-img relative h-full w-full object-cover">
        </div>
        <div class="order-1 p-9 lg:order-2 lg:p-12">
          <p class="text-[13px] uppercase tracking-[0.18em] text-teal">Untuk kaunselor</p>
          <h2 class="mt-6 text-[32px] leading-[1.08] tracking-tightest font-semibold lg:text-[48px]">Untuk kaunselor berdaftar</h2>
          <p class="mt-6 max-w-lg text-[17px] leading-relaxed text-navy/65">Buka praktis anda kepada pengguna di seluruh Malaysia, tanpa perlu membina sistem tempahan sendiri.</p>
          <ul class="mt-9 space-y-4 border-t border-navy/10 pt-8 text-[16px]">
            <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Pengesahan kelayakan diuruskan oleh platform</span></li>
            <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Buka slot mengikut jadual anda sendiri, atau terima permohonan masa</span></li>
            <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Planner peribadi yang menghalang pertindihan jadual</span></li>
            <li class="flex gap-3"><span class="mt-[9px] h-1.5 w-1.5 shrink-0 rounded-full bg-teal"></span><span>Sesi dalam talian atau bersemuka, mengikut pilihan anda</span></li>
          </ul>
          <div class="mt-10 flex flex-col gap-3 sm:flex-row">
            <a href="{{ $signup }}" class="inline-flex items-center justify-center rounded-full bg-navy px-8 py-4 text-[15px] font-medium text-cream transition-colors duration-300 hover:bg-teal">Sertai Sebagai Kaunselor</a>
            <a href="#features" class="inline-flex items-center justify-center rounded-full border border-navy/25 px-8 py-4 text-[15px] font-medium text-navy transition-colors duration-300 hover:border-navy hover:bg-navy/5">Ketahui lebih lanjut</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 13. MUAT TURUN APLIKASI (the app is not in the stores yet: config/registration.php, mobile_app_url) -->
<section id="get-the-app" class="relative overflow-hidden bg-navy grain text-cream">
  <div class="mx-auto max-w-7xl px-6 py-20 lg:px-10 lg:py-24">
    <div class="grid items-center gap-14 lg:grid-cols-12 lg:gap-16">
      <div class="reveal lg:col-span-6">
        <h2 class="text-[34px] leading-[1.08] tracking-tightest font-semibold sm:text-5xl lg:text-[58px]">Bawa sokongan itu bersama anda</h2>
        <p class="mt-6 max-w-lg text-[17px] leading-relaxed text-cream/70">Mulakan perbualan bila-bila masa, cari kaunselor berdaftar, dan uruskan tempahan anda terus dari telefon.</p>
        <div class="mt-9 flex flex-wrap items-center gap-4">
          <span class="lencana grid h-[52px] w-[176px] place-items-center rounded-xl border border-dashed border-cream/30 text-[11px] uppercase tracking-[0.14em] text-cream/65">App Store · Akan datang</span>
          <span class="lencana grid h-[52px] w-[176px] place-items-center rounded-xl border border-dashed border-cream/30 text-[11px] uppercase tracking-[0.14em] text-cream/65">Google Play · Akan datang</span>
        </div>
        <p class="mt-6 text-[14px] text-cream/55">Percuma untuk dimuat turun · Android dan iOS</p>
      </div>

      <div class="reveal lg:col-span-6">
        <div class="flex justify-center lg:justify-end">
          <div class="-rotate-4" style="filter: drop-shadow(0 40px 60px rgba(0,0,0,.45))">
            <div class="phone-shadow w-[268px] rounded-[42px] border-[10px] border-navy bg-navy sm:w-[300px]">
              <div class="relative overflow-hidden rounded-[32px] bg-cream">
                <div class="flex items-center justify-between px-5 pt-4 pb-1 text-[10px] font-medium text-navy/60">
                  <span>9:41</span>
                  <span class="flex gap-1"><i class="block h-1.5 w-1.5 rounded-full bg-navy/40"></i><i class="block h-1.5 w-1.5 rounded-full bg-navy/40"></i><i class="block h-1.5 w-1.5 rounded-full bg-navy/40"></i></span>
                </div>
                <div class="px-5 pt-4 pb-6">
                  <div class="flex items-center gap-2">
                    <img src="{{ asset('images/mykaunsel-icon.png') }}" alt="" class="h-[22px] w-auto shrink-0">
                    <span class="text-[13px] font-semibold">Kaunsel AI</span>
                  </div>
                  <div class="mt-5 space-y-3">
                    <div class="max-w-[85%] rounded-2xl rounded-tl-md bg-navy/[0.06] px-4 py-3 text-[11.5px] leading-relaxed text-navy/80">Hai. Apa yang anda rasa hari ini?</div>
                    <div class="ml-auto max-w-[80%] rounded-2xl rounded-tr-md bg-teal px-4 py-3 text-[11.5px] leading-relaxed text-cream">Saya rasa tertekan dengan peperiksaan.</div>
                    <div class="max-w-[88%] rounded-2xl rounded-tl-md bg-navy/[0.06] px-4 py-3 text-[11.5px] leading-relaxed text-navy/80">Terima kasih kerana berkongsi. Mahu saya tunjukkan kaunselor disahkan yang tersedia minggu ini?</div>
                  </div>
                  <div class="mt-5 rounded-2xl border border-navy/10 bg-white p-3">
                    <div class="flex items-center gap-3">
                      <span class="h-9 w-9 rounded-full bg-gradient-to-br from-teal/30 to-amber/30"></span>
                      <div class="leading-tight">
                        <p class="text-[11.5px] font-semibold">Kaunselor Berdaftar</p>
                        <p class="text-[10px] text-teal">Lesen amalan sah</p>
                      </div>
                    </div>
                    <div class="mt-3 rounded-xl bg-navy py-2.5 text-center text-[11px] font-medium text-cream">Tempah sesi</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 10. FAQ -->
<section id="faq" class="border-t border-navy/10 bg-cream py-20 lg:py-24">
  <div class="mx-auto max-w-7xl px-6 lg:px-10">
    <div class="grid gap-12 lg:grid-cols-12 lg:gap-20">
      <div class="lg:col-span-4">
        <h2 class="reveal text-[34px] leading-[1.08] tracking-tightest font-semibold sm:text-5xl lg:text-[52px]">Soalan lazim</h2>
      </div>
      <div class="lg:col-span-8" x-data="{ active: 0 }">
        <div class="border-t border-navy/15">
          @foreach ($faq as $n => [$question, $answer])
            <div class="border-b border-navy/15">
              <button type="button" x-on:click="active = active === {{ $n }} ? null : {{ $n }}" x-bind:aria-expanded="active === {{ $n }}" class="flex w-full items-start justify-between gap-6 py-7 text-left">
                <span class="text-lg font-medium tracking-tight lg:text-[22px]">{{ $question }}</span>
                <span class="relative mt-2 block h-4 w-4 shrink-0">
                  <span class="absolute left-0 top-1/2 h-[2px] w-4 -translate-y-1/2 bg-teal"></span>
                  <span class="absolute left-1/2 top-0 h-4 w-[2px] -translate-x-1/2 bg-teal transition-transform duration-300" x-bind:class="active === {{ $n }} ? 'rotate-90' : ''"></span>
                </span>
              </button>
              <div class="faq-panel" x-bind:class="active === {{ $n }} ? 'is-open' : ''">
                <div class="overflow-hidden">
                  <p class="max-w-2xl pb-8 leading-relaxed text-navy/65">{{ $answer }}</p>
                </div>
              </div>
            </div>
          @endforeach
        </div>
        <a href="{{ route('help') }}" class="reveal mt-10 inline-flex items-center gap-2 border-b border-navy/30 pb-1 text-[15px] text-navy transition-colors duration-300 hover:border-navy">Lihat semua soalan lazim <span aria-hidden="true">→</span></a>
      </div>
    </div>
  </div>
</section>

<!-- 11. TALIAN BANTUAN -->
<section class="bg-amber">
  <div class="mx-auto max-w-7xl px-6 py-12 lg:px-10 lg:py-14">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
      <p class="max-w-2xl text-[17px] leading-relaxed text-navy lg:text-xl">
        Jika anda memerlukan bantuan segera, hubungi Talian HEAL atau Talian Kasih. Bantuan tersedia sekarang.
      </p>
      <div class="flex flex-wrap gap-3">
        <a href="tel:15555" class="inline-flex items-center gap-2 rounded-full bg-navy px-7 py-3.5 text-[15px] font-medium text-cream transition-opacity hover:opacity-85">Talian HEAL <span class="tabular-nums">15555</span></a>
        <a href="tel:15999" class="inline-flex items-center gap-2 rounded-full border border-navy/40 px-7 py-3.5 text-[15px] font-medium text-navy transition-colors hover:bg-navy hover:text-cream">Talian Kasih <span class="tabular-nums">15999</span></a>
      </div>
    </div>
    <a href="{{ route('help') }}" class="mt-7 inline-flex items-center gap-2 border-b border-navy/35 pb-0.5 text-[14px] text-navy/80 transition-colors hover:border-navy hover:text-navy">Lihat semua sumber bantuan segera <span aria-hidden="true">→</span></a>
  </div>
</section>

<!-- 12. FOOTER -->
<footer class="bg-navy text-cream">
  <div class="mx-auto max-w-7xl px-6 py-14 lg:px-10 lg:py-16">
    <div class="grid gap-14 lg:grid-cols-12">
      <div class="lg:col-span-4">
        <div class="flex items-center gap-2.5">
          <img src="{{ asset('images/mykaunsel-icon.png') }}" alt="" class="h-9 w-auto shrink-0">
          <span class="text-[19px] font-semibold tracking-tightest text-cream">MyKaunsel</span>
        </div>
        <p class="mt-6 max-w-xs text-[22px] leading-snug tracking-tight text-cream/85 lg:text-[26px]">Sokongan kaunseling yang disahkan, untuk semua.</p>
      </div>
      <div class="grid gap-10 sm:grid-cols-2 lg:col-span-8 lg:grid-cols-4">
        <div>
          <p class="text-sm font-semibold">Produk</p>
          <ul class="mt-5 space-y-3 text-[15px] text-cream/55">
            <li><a href="#features" class="transition-colors hover:text-cream">Cara ia berfungsi</a></li>
            <li><a href="#features" class="transition-colors hover:text-cream">Cari kaunselor</a></li>
            <li><a href="{{ $appUrl }}" class="transition-colors hover:text-cream">Muat turun aplikasi</a></li>
            <li><a href="#pricing" class="transition-colors hover:text-cream">Harga</a></li>
          </ul>
        </div>
        <div>
          <p class="text-sm font-semibold">Untuk Organisasi</p>
          <ul class="mt-5 space-y-3 text-[15px] text-cream/55">
            <li><a href="#organizations" class="transition-colors hover:text-cream">Universiti</a></li>
            <li><a href="#organizations" class="transition-colors hover:text-cream">Klinik</a></li>
            <li><a href="#organizations" class="transition-colors hover:text-cream">Portal pengurusan</a></li>
            <li><a href="{{ $signup }}" class="transition-colors hover:text-cream">Daftar organisasi</a></li>
          </ul>
        </div>
        <div>
          <p class="text-sm font-semibold">Sumber</p>
          <ul class="mt-5 space-y-3 text-[15px] text-cream/55">
            <li><a href="#faq" class="transition-colors hover:text-cream">Soalan lazim</a></li>
            <li><a href="tel:15555" class="transition-colors hover:text-cream">Bantuan segera</a></li>
            <li><a href="#features" class="transition-colors hover:text-cream">Pengesahan kelayakan</a></li>
            <li><a href="{{ route('help') }}" class="transition-colors hover:text-cream">Hubungi kami</a></li>
          </ul>
        </div>
        <div>
          <p class="text-sm font-semibold">Syarikat</p>
          <ul class="mt-5 space-y-3 text-[15px] text-cream/55">
            <li><a href="#top" class="transition-colors hover:text-cream">Tentang kami</a></li>
            <li><a href="#counselors" class="transition-colors hover:text-cream">Untuk kaunselor</a></li>
            <li><a href="{{ route('privacy') }}" class="transition-colors hover:text-cream">Dasar privasi</a></li>
            <li><a href="{{ route('privacy') }}" class="transition-colors hover:text-cream">Notis PDPA</a></li>
            <li><a href="{{ route('terms') }}" class="transition-colors hover:text-cream">Terma Perkhidmatan</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="mt-14 border-t border-cream/15 pt-8">
      <div class="group relative inline-block" id="langSwitcher">
        <div class="absolute bottom-full left-0 w-48 pb-2 opacity-0 invisible transition-opacity duration-150 group-hover:opacity-100 group-hover:visible">
          <div class="overflow-hidden rounded-xl border border-cream/15 bg-[#161616] py-1.5" style="box-shadow:0 20px 40px -12px rgba(0,0,0,.55)">
            <button type="button" data-lang="ms" class="lang-opt flex w-full items-center rounded-lg px-3 py-2.5 text-left text-[14px] text-cream/90 transition-colors hover:bg-cream/10">Bahasa Melayu</button>
            <button type="button" data-lang="en" class="lang-opt flex w-full items-center rounded-lg px-3 py-2.5 text-left text-[14px] text-cream/90 transition-colors hover:bg-cream/10">English</button>
          </div>
        </div>
        <button type="button" id="langToggle" class="inline-flex items-center gap-2 rounded-full border border-cream/20 px-4 py-2.5 text-[14px] font-medium text-cream/85 transition-colors hover:border-cream/40" aria-label="Tukar bahasa / Switch language">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a13 13 0 0 1 3.5 9 13 13 0 0 1-3.5 9 13 13 0 0 1-3.5-9A13 13 0 0 1 12 3z"/></svg>
          <span id="langToggleLabel">Bahasa Melayu</span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
        </button>
      </div>
      <div class="mt-6 flex flex-col gap-4 text-[13px] text-cream/45 sm:flex-row sm:items-center sm:justify-between">
        <p>© {{ date('Y') }} MyKaunsel. Projek akademik.</p>
        <p>Kaunselor disahkan terhadap pendaftaran Lembaga Kaunselor Malaysia.</p>
      </div>
    </div>
  </div>
</footer>

<script src="{{ asset('js/i18n.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  // Which section is on screen lights up its menu link.
  var navLinks = [].slice.call(document.querySelectorAll('.nav-glass'));
  var targets = navLinks.map(function(a){ return document.querySelector(a.getAttribute('href')); }).filter(Boolean);
  if (targets.length && window.IntersectionObserver) {
    var spy = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (!e.isIntersecting) return;
        navLinks.forEach(function(a){ a.classList.toggle('is-active', a.getAttribute('href') === '#' + e.target.id); });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    targets.forEach(function(t){ spy.observe(t); });
  }

  // Blocks fade in as they come into view.
  var items = [].slice.call(document.querySelectorAll('.reveal'));
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !window.IntersectionObserver) {
    document.documentElement.classList.add('no-anim');
    return;
  }
  var seen = new IntersectionObserver(function(entries, obs){
    entries.forEach(function(e){ if (e.isIntersecting) { e.target.classList.add('is-in'); obs.unobserve(e.target); } });
  }, { rootMargin: '0px 0px -12% 0px' });
  items.forEach(function(el){ seen.observe(el); });
});
</script>
</body>
</html>
