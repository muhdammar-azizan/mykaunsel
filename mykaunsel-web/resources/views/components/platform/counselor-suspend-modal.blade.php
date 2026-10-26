@props(['name', 'action', 'counselorName', 'independent', 'upcoming', 'pendingRequests', 'bag' => 'suspend'])

{{-- Suspend a counselor: reason, what it means, and what happens to sessions already booked. Used on the Counselors and Complaints pages. --}}
<x-platform.reason-modal :name="$name" :action="$action" :bag="$bag"
    :title="'Suspend '.$counselorName.'?'" :textarea-id="$name.'-reason'" :rows="4"
    label="Reason (the counselor is told, and it is kept in the audit log)" placeholder="Why is this counselor being suspended?"
    submit-label="Suspend counselor" busy-label="Suspending…">
    <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
        <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> They stop appearing in search and cannot open new slots.</li>
        <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> Session requests nobody has answered yet ({{ $pendingRequests }}) are closed and the clients are emailed.</li>
        <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> {{ $counselorName }} is emailed{{ $independent ? '' : ', and so are the admins of their organization' }}.</li>
        <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Nothing is deleted, and you can reactivate them later.</li>
    </ul>

    <fieldset class="mt-4">
        <legend class="text-[12.5px] font-semibold text-brand-ink/70">Sessions already booked ({{ $upcoming }} upcoming)</legend>
        <label class="mt-2 flex cursor-pointer items-start gap-2.5 text-[13.5px]">
            <input type="radio" name="sessions" value="keep" checked class="mt-0.5 border-brand-ink/30 text-brand-blue focus:ring-brand-blue">
            <span><span class="font-semibold">Keep existing sessions</span> <span class="text-brand-ink/55">(default) — they stay booked.</span></span>
        </label>
        <label class="mt-2 flex cursor-pointer items-start gap-2.5 text-[13.5px]">
            <input type="radio" name="sessions" value="cancel_upcoming" class="mt-0.5 border-brand-ink/30 text-brand-red focus:ring-brand-red">
            <span><span class="font-semibold">Cancel upcoming sessions and notify clients</span> <span class="text-brand-ink/55">— each client is emailed the counselor's name and the time only.</span></span>
        </label>
    </fieldset>
    {{ $slot }}
</x-platform.reason-modal>
