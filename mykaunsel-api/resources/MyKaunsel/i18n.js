(function(){
  var STORAGE_KEY = 'mk_lang';

  var TEXT = {
    "Ciri":"Features",
    "Untuk Organisasi":"For Organizations",
    "Untuk Kaunselor":"For Counselors",
    "Harga":"Pricing",
    "Soalan Lazim":"FAQ",
    "Log Masuk":"Log In",
    "Daftar":"Sign Up",
    "Daftar sebagai Organisasi":"Sign up as an Organization",
    "Daftar sebagai Kaunselor":"Sign up as a Counselor",
    "Muat Turun Aplikasi":"Download App",
    "Platform kaunseling Malaysia":"Malaysia's counseling platform",
    "VIDEO: individu menggunakan aplikasi":"VIDEO: individual using the app",
    "VIDEO: pasukan unit kaunseling":"VIDEO: counseling unit team",
    "VIDEO: kaunselor berdaftar bekerja":"VIDEO: registered counselor at work",
    "Kaunselor disahkan":"Verified counselors",
    "Setiap profil disemak terhadap pendaftaran Lembaga Kaunselor Malaysia":"Every profile is checked against Lembaga Kaunselor Malaysia registration",
    "Perbualan peribadi":"Private conversations",
    "Apa yang anda tulis tidak dikongsi dengan kaunselor atau organisasi":"What you write isn't shared with counselors or organizations",
    "Mematuhi PDPA":"PDPA compliant",
    "Data dikumpul dan disimpan mengikut Akta Perlindungan Data Peribadi":"Data is collected and stored under the Personal Data Protection Act",
    "Bantuan itu ada. Jalan ke arahnya yang selalu tersekat.":"Help exists. The path to it keeps getting blocked.",
    "Tidak tahu mula":"Don't know where to start",
    "Ramai tidak pasti sama ada apa yang dirasai cukup \"serius\" untuk dibawa kepada kaunselor.":"Many aren't sure if what they're feeling is \"serious\" enough to bring to a counselor.",
    "Sukar cari":"Hard to find",
    "Maklumat kaunselor bertaburan — nombor telefon, media sosial, cadangan mulut ke mulut.":"Counselor information is scattered — phone numbers, social media, word of mouth.",
    "Risau privasi":"Privacy concerns",
    "Kebimbangan tentang siapa yang akan tahu sering menghentikan langkah pertama.":"Worry about who might find out often stops the first step.",
    "Satu platform, dari perbualan pertama sehingga sesi selesai":"One platform, from the first conversation to the completed session",
    "Setiap peringkat direka supaya langkah seterusnya sentiasa jelas — untuk pengguna, kaunselor, dan organisasi.":"Every stage is designed so the next step is always clear — for users, counselors, and organizations.",
    "GAMBAR: perbualan chatbot":"IMAGE: chatbot conversation",
    "GAMBAR: pengesahan kaunselor":"IMAGE: counselor verification",
    "GAMBAR: skrin tempahan":"IMAGE: booking screen",
    "GAMBAR: planner kaunselor":"IMAGE: counselor planner",
    "GAMBAR: portal organisasi":"IMAGE: organization portal",
    "GAMBAR: kaunselor berdaftar":"IMAGE: registered counselor",
    "Perbualan awal":"Initial conversation",
    "Tempahan sesi":"Session booking",
    "Planner kaunselor":"Counselor planner",
    "Portal organisasi":"Organization portal",
    "Chatbot AI menjadi titik masuk yang paling rendah halangannya. Pengguna menaip apa yang dirasai, dan sistem membantu menyusun keadaan tersebut menjadi langkah yang boleh diambil.":"The AI chatbot is the lowest-barrier entry point. Users type what they're feeling, and the system helps turn that into steps they can take.",
    "Ketahui cara chatbot berfungsi":"Learn how the chatbot works",
    "Setiap kaunselor disemak terhadap pendaftaran Lembaga Kaunselor Malaysia, termasuk lesen amalan yang masih berkuat kuasa. Profil yang gagal disemak tidak dipaparkan.":"Every counselor is checked against Lembaga Kaunselor Malaysia registration, including an active practicing license. Profiles that fail the check aren't shown.",
    "Ketahui proses pengesahan":"Learn about the verification process",
    "Tapis kaunselor mengikut bidang, bahasa dan lokasi. Pilih slot yang tersedia, tetapkan mod sesi dalam talian atau bersemuka, dan terima pengesahan serta-merta.":"Filter counselors by specialty, language, and location. Pick an available slot, set the session mode to online or in person, and get instant confirmation.",
    "Lihat cara tempahan":"See how booking works",
    "Satu kalendar untuk slot yang dibuka, sesi yang diterima, dan komitmen peribadi. Kaunselor mengawal ketersediaan mereka tanpa pertindihan jadual.":"One calendar for open slots, accepted sessions, and personal commitments. Counselors control their availability without schedule conflicts.",
    "Ketahui tentang planner":"Learn about the planner",
    "Universiti dan klinik menambah kaunselor, menetapkan polisi akses, dan memantau rekod tempahan perkhidmatan mereka dari satu tempat.":"Universities and clinics add counselors, set access policies, and track their service's booking records in one place.",
    "Ketahui tentang portal":"Learn about the portal",
    "Pengesahan kelayakan":"Credential verification",
    "Setiap kaunselor disahkan dahulu.":"Every counselor is verified first.",
    "Kami semak setiap kaunselor dengan Lembaga Kaunselor Malaysia. Yang tidak lulus semakan tidak akan muncul dalam aplikasi.":"We check every counselor against Lembaga Kaunselor Malaysia. Those who don't pass the check won't appear in the app.",
    "Nombor pendaftaran disemak":"Registration number checked",
    "Lesen amalan sah":"Valid practicing license",
    "Semakan berkala":"Periodic re-checks",
    "Ketahui lebih lanjut tentang proses pengesahan":"Learn more about the verification process",
    "VIDEO: proses pengesahan kaunselor":"VIDEO: counselor verification process",
    "Akses bergantung pada cara anda menyertai":"Access depends on how you join",
    "Organisasi menyediakan kaunselor untuk ahli mereka sendiri. Kaunselor bebas dan klinik terbuka kepada semua pengguna platform.":"Organizations provide counselors for their own members. Independent counselors and clinics are open to all platform users.",
    "Ahli organisasi":"Organization members",
    "Anda menyertai melalui domain e-mel institusi atau kod jemputan.":"You join through your institution's email domain or an invite code.",
    "Boleh tempah":"Can book",
    "Kaunselor organisasi anda":"Your organization's counselors",
    "Kaunselor bebas dan klinik":"Independent counselors and clinics",
    "Pengguna awam":"Public users",
    "Anda mendaftar sendiri dengan e-mel peribadi.":"You sign up yourself with a personal email.",
    "Kaunselor organisasi hanya terbuka kepada ahli organisasi tersebut.":"Organization counselors are only open to that organization's members.",
    "Harga yang jelas untuk setiap laluan":"Clear pricing for every path",
    "Individu":"Individual",
    "Percuma untuk dimuat turun":"Free to download",
    "Chatbot sokongan tanpa had":"Unlimited support chatbot",
    "Cari kaunselor berdaftar":"Find registered counselors",
    "Bayaran hanya untuk sesi yang ditempah":"Pay only for sessions you book",
    "Muat turun aplikasi":"Download the app",
    "Organisasi":"Organization",
    "Langganan tahunan":"Annual subscription",
    "Kaunselor tanpa had dalam portal":"Unlimited counselors in the portal",
    "Pengurusan ahli dan ketersediaan":"Member and availability management",
    "Rekod tempahan dan laporan":"Booking records and reports",
    "Harga mengikut saiz keahlian":"Pricing based on membership size",
    "Hubungi kami":"Contact us",
    "Kaunselor":"Counselor",
    "Percuma untuk menyertai":"Free to join",
    "Pengesahan kelayakan disediakan":"Credential verification handled for you",
    "Planner dan pengurusan sesi":"Planner and session management",
    "Komisen kecil setiap sesi yang selesai":"A small commission per completed session",
    "Sertai sebagai kaunselor":"Join as a counselor",
    "Untuk organisasi":"For organizations",
    "Untuk universiti dan klinik":"For universities and clinics",
    "Bawa perkhidmatan kaunseling anda yang sudah ada ke dalam satu sistem tempahan dan pengurusan.":"Bring your existing counseling service into one booking and management system.",
    "Daftar Organisasi Anda":"Register Your Organization",
    "Ketahui lebih lanjut":"Learn more",
    "Senaraikan kaunselor organisasi dan uruskan ketersediaan mereka dari satu tempat.":"List your organization's counselors and manage their availability from one place.",
    "Terima tempahan pelajar atau pesakit tanpa urusan melalui telefon dan e-mel.":"Accept student or patient bookings without back-and-forth phone calls and emails.",
    "Semak rekod sesi dan jadual untuk perancangan sumber.":"Review session records and schedules for resource planning.",
    "Kaunselor yang didaftarkan melalui proses pengesahan kelayakan yang sama.":"Counselors go through the same credential verification process.",
    "Akses terhad kepada maklumat tempahan sahaja — kandungan chat kekal peribadi.":"Access is limited to booking information only — chat content stays private.",
    "Untuk kaunselor":"For counselors",
    "Untuk kaunselor berdaftar":"For registered counselors",
    "Buka praktis anda kepada pengguna di seluruh Malaysia, tanpa perlu membina sistem tempahan sendiri.":"Open your practice to users across Malaysia, without building your own booking system.",
    "Pengesahan kelayakan diuruskan oleh platform":"Credential verification handled by the platform",
    "Buka slot mengikut jadual anda sendiri, atau terima permohonan masa":"Open slots on your own schedule, or accept time requests",
    "Planner peribadi yang menghalang pertindihan jadual":"A personal planner that prevents schedule conflicts",
    "Sesi dalam talian atau bersemuka, mengikut pilihan anda":"Online or in-person sessions, your choice",
    "Sertai Sebagai Kaunselor":"Join As A Counselor",
    "Bawa sokongan itu bersama anda":"Bring that support with you",
    "Mulakan perbualan bila-bila masa, cari kaunselor berdaftar, dan uruskan tempahan anda terus dari telefon.":"Start a conversation anytime, find a registered counselor, and manage your bookings right from your phone.",
    "Lencana App Store":"App Store badge",
    "Lencana Google Play":"Google Play badge",
    "Percuma untuk dimuat turun · Android dan iOS":"Free to download · Android and iOS",
    "Hai. Apa yang anda rasa hari ini?":"Hi. How are you feeling today?",
    "Saya rasa tertekan dengan peperiksaan.":"I'm feeling stressed about exams.",
    "Terima kasih kerana berkongsi. Mahu saya tunjukkan kaunselor disahkan yang tersedia minggu ini?":"Thanks for sharing. Want me to show verified counselors available this week?",
    "Kaunselor Berdaftar":"Registered Counselor",
    "Tempah sesi":"Book a session",
    "Soalan lazim":"FAQ",
    "Adakah kaunselor dalam MyKaunsel berdaftar?":"Are the counselors on MyKaunsel registered?",
    "Ya. Setiap kaunselor disahkan terhadap pendaftaran Lembaga Kaunselor Malaysia, termasuk lesen amalan yang sah, sebelum profil mereka dipaparkan.":"Yes. Every counselor is verified against Lembaga Kaunselor Malaysia registration, including a valid practicing license, before their profile is shown.",
    "Apa yang berlaku kepada perbualan saya dengan chatbot?":"What happens to my conversation with the chatbot?",
    "Perbualan chat adalah peribadi. Ia tidak dikongsi dengan kaunselor atau organisasi, dan data disimpan mengikut Akta Perlindungan Data Peribadi (PDPA).":"Chat conversations are private. They aren't shared with counselors or organizations, and data is stored under the Personal Data Protection Act (PDPA).",
    "Bolehkah saya memilih sesi bersemuka?":"Can I choose an in-person session?",
    "Boleh. Semasa menempah, anda memilih mod sesi — dalam talian atau bersemuka — berdasarkan slot yang dibuka oleh kaunselor.":"Yes. When booking, you choose the session mode — online or in person — based on the slots the counselor has opened.",
    "Bagaimana organisasi mendaftar?":"How do organizations register?",
    "Organisasi mendaftar melalui borang pendaftaran organisasi, kemudian menambah kaunselor mereka ke dalam portal pengurusan untuk membuka tempahan.":"Organizations register through the organization sign-up form, then add their counselors to the management portal to open bookings.",
    "Apa yang organisasi dapat lihat tentang pengguna?":"What can organizations see about users?",
    "Portal organisasi memaparkan maklumat tempahan dan jadual sahaja. Kandungan perbualan chat tidak boleh diakses oleh organisasi.":"The organization portal only shows booking and schedule information. Chat conversation content can't be accessed by the organization.",
    "Saya seorang kaunselor. Apa yang saya perlukan untuk menyertai?":"I'm a counselor. What do I need to join?",
    "Anda perlu memberikan maklumat pendaftaran dan lesen amalan anda untuk disemak. Setelah disahkan, anda boleh membuka slot dan menguruskan sesi melalui planner.":"You'll need to provide your registration details and practicing license for review. Once verified, you can open slots and manage sessions through the planner.",
    "Berapakah kos menggunakan MyKaunsel?":"How much does MyKaunsel cost?",
    "Aplikasi percuma untuk dimuat turun dan chatbot boleh digunakan tanpa kos. Bayaran hanya dikenakan untuk sesi yang ditempah dengan kaunselor bebas atau klinik, mengikut kadar yang ditetapkan kaunselor tersebut. Ahli organisasi biasanya mendapat sesi kaunselor institusi tanpa kos.":"The app is free to download and the chatbot is free to use. Fees only apply to sessions booked with independent counselors or clinics, at rates set by that counselor. Organization members usually get their institution's counselor sessions at no cost.",
    "Bolehkah saya guna MyKaunsel jika universiti saya belum menyertai?":"Can I use MyKaunsel if my university hasn't joined yet?",
    "Boleh. Anda mendaftar sebagai pengguna awam dan boleh menempah sesi dengan kaunselor bebas serta klinik yang tersedia di platform.":"Yes. You sign up as a public user and can book sessions with independent counselors and clinics available on the platform.",
    "Lihat semua soalan lazim":"View all FAQs",
    "Jika anda memerlukan bantuan segera, hubungi Talian HEAL atau Talian Kasih. Bantuan tersedia sekarang.":"If you need immediate help, call Talian HEAL or Talian Kasih. Help is available now.",
    "Lihat semua sumber bantuan segera":"View all crisis resources",
    "Sokongan kaunseling yang disahkan, untuk semua.":"Verified counseling support, for everyone.",
    "Produk":"Product",
    "Cara ia berfungsi":"How it works",
    "Cari kaunselor":"Find a counselor",
    "Universiti":"Universities",
    "Klinik":"Clinics",
    "Portal pengurusan":"Management portal",
    "Daftar organisasi":"Register your organization",
    "Sumber":"Resources",
    "Bantuan segera":"Immediate help",
    "Syarikat":"Company",
    "Tentang kami":"About us",
    "Dasar privasi":"Privacy policy",
    "Notis PDPA":"PDPA notice",
    "Terma Perkhidmatan":"Terms of Service",
    "© 2026 MyKaunsel. Projek akademik.":"© 2026 MyKaunsel. Academic project.",
    "Kaunselor disahkan terhadap pendaftaran Lembaga Kaunselor Malaysia.":"Counselors are verified against Lembaga Kaunselor Malaysia registration."
  };

  var ATTR = {
    "Kad sebelumnya":"Previous card",
    "Kad seterusnya":"Next card",
    "Saya mencari sokongan":"I'm looking for support",
    "Kami sebuah organisasi":"We're an organization",
    "Saya seorang kaunselor":"I'm a counselor",
    "Muat turun di App Store":"Download on the App Store",
    "Dapatkan di Google Play":"Get it on Google Play"
  };

  var TITLE = { ms: "MyKaunsel — Sokongan kaunseling yang disahkan", en: "MyKaunsel — Verified counseling support" };

  var STORAGE_KEY_NAME = STORAGE_KEY;
  var recs = [];

  function collect(){
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
    var n;
    while ((n = walker.nextNode())) {
      var p = n.parentElement;
      if (!p) continue;
      var tag = p.tagName;
      if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT' || tag === 'TEMPLATE') continue;
      if (p.closest && p.closest('svg')) continue;
      var trimmed = n.textContent.trim();
      if (trimmed && Object.prototype.hasOwnProperty.call(TEXT, trimmed)) {
        recs.push({ node: n, ms: n.textContent, trimmed: trimmed });
      }
    }
  }

  function applyLang(lang){
    recs.forEach(function(r){
      if (lang === 'en') {
        var idx = r.node.textContent.indexOf(r.trimmed);
        if (idx >= 0) {
          r.node.textContent = r.node.textContent.slice(0, idx) + TEXT[r.trimmed] + r.node.textContent.slice(idx + r.trimmed.length);
        }
      } else {
        r.node.textContent = r.ms;
      }
    });

    Object.keys(ATTR).forEach(function(msVal){
      ['aria-label','alt','title','placeholder'].forEach(function(attr){
        document.querySelectorAll('[' + attr + '="' + msVal.replace(/"/g,'\\"') + '"]').forEach(function(el){
          el.setAttribute(attr, lang === 'en' ? ATTR[msVal] : msVal);
        });
      });
    });
    ['aria-label','alt','title','placeholder'].forEach(function(attr){
      document.querySelectorAll('[' + attr + ']').forEach(function(el){
        var v = el.getAttribute(attr);
        if (v && Object.prototype.hasOwnProperty.call(TEXT, v)) {
          el.setAttribute(attr, lang === 'en' ? TEXT[v] : v);
        }
      });
    });

    document.title = lang === 'en' ? TITLE.en : TITLE.ms;
    document.documentElement.setAttribute('lang', lang === 'en' ? 'en' : 'ms');
    updateSwitcherUI(lang);
    if (window.__mkSetHeroLang) window.__mkSetHeroLang(lang);
    localStorage.setItem(STORAGE_KEY_NAME, lang);
  }

  function updateSwitcherUI(lang){
    var label = document.getElementById('langToggleLabel');
    if (label) label.textContent = lang === 'en' ? 'English' : 'Bahasa Melayu';
    document.querySelectorAll('.lang-opt').forEach(function(b){
      b.classList.toggle('bg-cream/15', b.getAttribute('data-lang') === lang);
    });
  }

  function init(){
    collect();
    var lang = localStorage.getItem(STORAGE_KEY_NAME) || 'ms';
    updateSwitcherUI(lang);
    if (lang === 'en') applyLang('en');

    document.querySelectorAll('.lang-opt').forEach(function(b){
      b.addEventListener('click', function(){
        applyLang(b.getAttribute('data-lang'));
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
