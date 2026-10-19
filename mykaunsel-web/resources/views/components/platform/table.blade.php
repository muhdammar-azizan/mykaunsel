@props(['headings', 'empty' => 'Nothing to show yet.'])

<div class="mt-3 overflow-x-auto rounded-xl border border-brand-ink/10">
    <table class="w-full min-w-[420px] text-left">
        <thead class="bg-cream">
            <tr>
                @foreach ($headings as $heading)
                    <th scope="col" class="px-4 py-3 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/70">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if ($slot->isEmpty())
                <tr><td colspan="{{ count($headings) }}" class="px-4 py-8 text-center text-[13px] text-brand-ink/50">{{ $empty }}</td></tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
