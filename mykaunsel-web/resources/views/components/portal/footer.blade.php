{{-- Stays at the bottom of every portal page. --}}
<footer class="flex h-[40px] shrink-0 items-center justify-between gap-4 border-t border-[#E4E2DE] bg-[#F5F4F2] px-4 text-[12.5px] text-brand-ink/80 sm:px-6">
    <nav class="flex items-center gap-5" aria-label="Footer">
        <a href="{{ route('terms') }}" class="hover:underline focus:outline-none focus-visible:underline">Terms of Use</a>
        <a href="{{ route('privacy') }}" class="hover:underline focus:outline-none focus-visible:underline">Privacy</a>
        <a href="{{ route('help') }}" class="hover:underline focus:outline-none focus-visible:underline">Help</a>
    </nav>
    <p class="hidden truncate sm:block">Copyright © {{ now()->year }} MyKaunsel. All rights reserved.</p>
</footer>
