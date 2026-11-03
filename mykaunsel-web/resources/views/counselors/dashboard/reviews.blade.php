@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white';
    $outline = 'inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
    $stars = fn (int $n) => str_repeat('★', $n).str_repeat('☆', 5 - $n);
@endphp

<x-portal-layout title="Reviews">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Reviews</h1>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">What clients wrote after a session, without their names. Only you and the MyKaunsel team read the comments. Your organization never sees them. You can reply once, and flag a review that is abusive, false or about another counselor: MyKaunsel removes a review only if it breaks the guidelines, never because the stars are low.</p>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <section class="{{ $card }} mt-5 p-6" aria-labelledby="summary-title">
        <h2 id="summary-title" class="sr-only">Summary</h2>
        @if ($summary['count'] === 0)
            <x-empty-state-reviews title="No reviews yet." text="Clients are asked for a review the day after a session." class="!py-6" />
        @else
            <div class="grid gap-6 sm:grid-cols-[auto_1fr] sm:items-center">
                <div>
                    <p class="text-[40px] font-bold leading-none tabular-nums">{{ number_format($summary['average'], 1) }}</p>
                    <p class="mt-1 text-[18px] tracking-wide text-brand-yellow" aria-hidden="true">{{ $stars((int) round($summary['average'])) }}</p>
                    <p class="text-[13px] text-brand-ink/65">{{ $summary['count'] }} {{ \Illuminate\Support\Str::plural('review', $summary['count']) }}</p>
                </div>
                <div class="space-y-1.5">
                    @foreach ($summary['distribution'] as $n => $total)
                        <div class="flex items-center gap-3 text-[13px]">
                            <span class="w-14 shrink-0 text-brand-ink/70">{{ $n }} {{ $n === 1 ? 'star' : 'stars' }}</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-brand-ink/10"><span class="block h-full rounded-full bg-brand-blue" style="width: {{ $summary['count'] > 0 ? round($total / $summary['count'] * 100) : 0 }}%"></span></span>
                            <span class="w-8 text-right tabular-nums text-brand-ink/70">{{ $total }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @unless ($summary['enough'])
                <p class="mt-4 rounded-lg bg-[#FDF4E3] px-4 py-2.5 text-[13px] font-semibold text-[#6B4C0C]">Your average is shown to the public and to organizations only from {{ $summary['needed'] }} reviews ({{ $summary['count'] }} so far).</p>
            @endunless
        @endif
    </section>

    <div class="mt-5 space-y-4">
        @foreach ($reviews as $r)
            <article class="{{ $card }} p-6" aria-label="Review of {{ $r['date'] }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[18px] tracking-wide text-brand-yellow"><span aria-hidden="true">{{ $stars($r['rating']) }}</span><span class="sr-only">{{ $r['rating'] }} out of 5 stars</span></p>
                    <p class="text-[13px] text-brand-ink/55">{{ $r['date'] }}@unless ($r['attendance_confirmed']) · attendance not confirmed @endunless</p>
                </div>

                @if (count($r['tags']))
                    <p class="mt-2 flex flex-wrap gap-2">
                        @foreach ($r['tags'] as $tag)<span class="rounded-full bg-brand-blue/10 px-2.5 py-0.5 text-[12px] font-semibold text-brand-blue">{{ $tag }}</span>@endforeach
                    </p>
                @endif

                @if ($r['comment_held'])
                    <p class="mt-3 text-[14px] italic text-brand-ink/60">The comment is waiting for the MyKaunsel team to read it.</p>
                @elseif ($r['comment'])
                    <p class="mt-3 whitespace-pre-line text-[14.5px] leading-relaxed">{{ $r['comment'] }}</p>
                @endif

                @if ($r['reply'])
                    <div class="mt-4 rounded-lg bg-brand-ink/[.04] px-4 py-3">
                        <p class="text-[12.5px] font-semibold text-brand-ink/65">Your reply · {{ $r['replied_on'] }}</p>
                        <p class="mt-1 whitespace-pre-line text-[14px]">{{ $r['reply'] }}</p>
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap items-start gap-3 border-t border-[#EEEDEA] pt-4">
                    @if ($r['can_reply'])
                        <details class="min-w-[260px] flex-1">
                            <summary class="{{ $outline }} cursor-pointer list-none">Reply</summary>
                            <form method="POST" action="{{ route('counselor.reviews.reply', $r['id']) }}" class="mt-3">
                                @csrf
                                <label for="reply-{{ $r['id'] }}" class="block text-[13px] font-semibold text-brand-ink/70">Your public reply (one only, it can not be changed)</label>
                                <textarea id="reply-{{ $r['id'] }}" name="reply" rows="3" maxlength="{{ $replyMax }}" required class="mt-1 block w-full rounded-md border border-brand-ink/20 px-3 py-2 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20"></textarea>
                                <p class="mt-1 text-[12.5px] text-brand-ink/55">Be kind and factual. Do not write anything that could tell who the client is.</p>
                                <button class="mt-3 inline-flex min-h-[40px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white">Publish reply</button>
                            </form>
                        </details>
                    @endif

                    @if ($r['flag_status'])
                        <p class="text-[13px] font-semibold text-brand-ink/70">Sent to MyKaunsel ({{ $r['flag_reason'] }}) · {{ ['open' => 'being looked at', 'upheld' => 'review removed', 'dismissed' => 'kept after checking'][$r['flag_status']] ?? $r['flag_status'] }}</p>
                    @else
                        <details>
                            <summary class="cursor-pointer list-none text-[13px] font-semibold text-brand-blue hover:underline">Flag as unfair</summary>
                            <form method="POST" action="{{ route('counselor.reviews.flag', $r['id']) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                @csrf
                                <div>
                                    <label for="flag-{{ $r['id'] }}" class="block text-[13px] font-semibold text-brand-ink/70">Why</label>
                                    <select id="flag-{{ $r['id'] }}" name="reason" required class="mt-1 block min-h-[40px] rounded-md border border-brand-ink/20 pl-3 pr-9 text-[14px]">
                                        @foreach ($flagReasons as $key => $text)<option value="{{ $key }}">{{ $text }}</option>@endforeach
                                    </select>
                                </div>
                                <button class="{{ $outline }}">Send to MyKaunsel</button>
                            </form>
                        </details>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if ($reviews->hasPages())
        <div class="mt-5">{{ $reviews->links() }}</div>
    @endif
</x-portal-layout>
