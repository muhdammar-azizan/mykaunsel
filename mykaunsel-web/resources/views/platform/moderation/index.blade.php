@php
    $date = fn ($value) => \Illuminate\Support\Carbon::parse($value, 'UTC')->timezone($timezone)->format('j M Y, g:i A');
    $reasonLabels = ['spam' => 'Spam', 'harassment' => 'Harassment or bullying', 'self_harm' => 'Self-harm or someone in danger', 'inappropriate' => 'Inappropriate content', 'other' => 'Something else'];
    $tabUrl = fn (string $key) => route('platform.moderation', $key === 'pending' ? [] : ['tab' => $key]);
    $inputClass = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
@endphp

<x-platform-layout title="Moderate Feed">
    <x-platform.page-header title="Moderate Feed" subtitle="Posts and comments people reported. Only Platform Admins can read them; reporters are never shown." />

    <x-platform.status-tabs
        :tabs="[
            ['key' => 'pending', 'label' => 'To review', 'count' => $counts['pending'], 'url' => $tabUrl('pending')],
            ['key' => 'handled', 'label' => 'Handled', 'count' => $counts['handled'], 'url' => $tabUrl('handled')],
        ]"
        :active="$tab"
        label="Report status" />

    <div class="mt-5 space-y-4">
        @forelse ($groups as $group)
            @php
                $kind = $group->feed_post_id ? 'post' : 'comment';
                $item = $kind === 'post' ? $posts->get($group->feed_post_id) : $comments->get($group->feed_comment_id);
                $id = $kind === 'post' ? $group->feed_post_id : $group->feed_comment_id;
                $given = $reasons[$kind.':'.$id] ?? [];
                $where = $item?->removed_at ? 'Removed by a moderator' : ($item?->deleted_at ? 'Deleted by its author' : 'Visible in the Feed');
                $pending = $tab === 'pending';
            @endphp
            <x-platform.panel>
                <div class="flex flex-wrap items-center gap-2 text-[12.5px]">
                    <span class="rounded-full bg-brand-blue/10 px-2.5 py-0.5 font-bold uppercase tracking-wide text-brand-blue">{{ $kind }}</span>
                    <span class="font-semibold">{{ $item?->user?->name ?? 'Unknown author' }}</span>
                    @if ($item)<span class="text-brand-ink/55">{{ $date($item->created_at) }}</span>@endif
                    <span class="text-brand-ink/55">· {{ $where }}</span>
                    <span class="ml-auto font-semibold">{{ $group->reports }} {{ \Illuminate\Support\Str::plural('report', $group->reports) }}</span>
                </div>

                @if ($item)
                    <blockquote class="mt-3 whitespace-pre-line rounded-xl bg-brand-ink/[.04] px-4 py-3 text-[14.5px]">{{ $item->content }}</blockquote>
                @else
                    <p class="mt-3 text-[13px] text-brand-ink/55">This {{ $kind }} no longer exists.</p>
                @endif

                <ul class="mt-3 flex flex-wrap gap-2" aria-label="Reasons given">
                    @foreach ($given as $key => $n)
                        @php [$status, $reason] = explode('|', $key); @endphp
                        <li class="rounded-full border border-brand-ink/10 px-3 py-1 text-[12px] font-semibold text-brand-ink/70">{{ $reasonLabels[$reason] ?? $reason }} × {{ $n }}</li>
                    @endforeach
                </ul>

                @if ($pending)
                    <div class="mt-4 flex flex-wrap items-start gap-3">
                        <form method="POST" action="{{ route('platform.moderation.dismiss', [$kind, $id]) }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-brand-ink/15 px-4 py-2 text-[13.5px] font-semibold transition hover:border-brand-blue hover:text-brand-blue">Set reports aside</button>
                        </form>

                        <details class="min-w-[260px] flex-1" @if ($errors->hasBag('remove'.$kind.$id)) open @endif>
                            <summary class="inline-flex cursor-pointer rounded-full border border-brand-red/40 px-4 py-2 text-[13.5px] font-semibold text-brand-red">Remove {{ $kind }}…</summary>
                            <form method="POST" action="{{ route('platform.moderation.remove', [$kind, $id]) }}" class="mt-3">
                                @csrf
                                <label for="note-{{ $kind }}-{{ $id }}" class="block text-[12.5px] font-semibold text-brand-ink/70">Note to the author (optional, emailed)</label>
                                <textarea id="note-{{ $kind }}-{{ $id }}" name="note" rows="3" maxlength="1000" class="{{ $inputClass }}"></textarea>
                                <button type="submit" class="mt-3 rounded-full bg-brand-red px-4 py-2 text-[13.5px] font-semibold text-white">Remove for everyone</button>
                            </form>
                        </details>

                        <details class="min-w-[260px] flex-1" @if ($errors->hasBag('warn'.$kind.$id)) open @endif>
                            <summary class="inline-flex cursor-pointer rounded-full border border-brand-yellow px-4 py-2 text-[13.5px] font-semibold text-[#8A6210]">Warn author…</summary>
                            <form method="POST" action="{{ route('platform.moderation.warn', [$kind, $id]) }}" class="mt-3">
                                @csrf
                                <label for="warn-{{ $kind }}-{{ $id }}" class="block text-[12.5px] font-semibold text-brand-ink/70">Warning (emailed to the author; the {{ $kind }} stays)</label>
                                <textarea id="warn-{{ $kind }}-{{ $id }}" name="message" rows="3" maxlength="1000" required class="{{ $inputClass }}"></textarea>
                                @error('message', 'warn'.$kind.$id)<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
                                <button type="submit" class="mt-3 rounded-full bg-brand-blue px-4 py-2 text-[13.5px] font-semibold text-white">Send warning</button>
                            </form>
                        </details>
                    </div>
                @else
                    <p class="mt-4 text-[13px] text-brand-ink/60">
                        {{ $group->status === 'dismissed' ? 'Reports set aside' : 'Acted on' }}@if ($group->handled_at) · {{ $date($group->handled_at) }}@endif
                    </p>
                @endif
            </x-platform.panel>
        @empty
            <x-platform.panel class="py-14 text-center">
                <p class="text-[15px] font-bold">{{ $tab === 'pending' ? 'Nothing to review' : 'Nothing handled yet' }}</p>
                <p class="mt-1 text-[13px] text-brand-ink/55">{{ $tab === 'pending' ? 'Reports from the app will show up here.' : 'Posts and comments you have dealt with are listed here.' }}</p>
            </x-platform.panel>
        @endforelse

        @if ($groups->hasPages())
            <div class="mt-5">{{ $groups->links() }}</div>
        @endif
    </div>
</x-platform-layout>
