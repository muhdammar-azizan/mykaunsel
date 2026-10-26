@php
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 text-[14px] text-brand-ink focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $label = 'block text-[12.5px] font-semibold text-brand-ink/70';
    $value = fn (string $field) => old($field, $record->{$field} instanceof \DateTimeInterface ? $record->{$field}->format('Y-m-d') : $record->{$field});
@endphp

{{-- The checks while typing are a help only: the server checks everything again when the form is sent. --}}
<div class="grid gap-4 sm:grid-cols-2"
     x-data="{
        kb: @js($value('kb_number') ?? ''),
        pa: @js($value('pa_number') ?? ''),
        from: @js($value('pa_valid_from') ?? ''),
        until: @js($value('pa_valid_until') ?? ''),
        editing: @js($editing),
        except: @js($editing ? $record->id : null),
        found: { kb: null, pa: [] },
        seq: 0,
        get kbBad() { return ! this.editing && this.kb !== '' && ! /^KB\d{5}$/.test(this.kb) },
        get paBad() { return this.pa !== '' && ! /^PA\d{5}$/.test(this.pa) },
        get datesBad() { return this.from !== '' && this.until !== '' && this.until < this.from },
        async check() {
            const kb = this.editing ? '' : this.kb;
            const params = new URLSearchParams();
            if (/^KB\d{5}$/.test(kb)) params.set('kb', kb);
            if (/^PA\d{5}$/.test(this.pa)) params.set('pa', this.pa);
            if (! params.size) { this.found = { kb: null, pa: [] }; return }
            if (this.except) params.set('except', this.except);
            const turn = ++this.seq;
            try {
                const response = await fetch(@js(route('platform.lkm.lookup')) + '?' + params, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (response.ok && turn === this.seq) this.found = await response.json();
            } catch (e) { /* offline or signed out: the server still checks on save */ }
        },
     }"
     x-init="check()">
    <div>
        <label for="kb_number" class="{{ $label }}">KB number</label>
        @if ($editing)
            <input id="kb_number" type="text" value="{{ $record->kb_number }}" disabled class="{{ $input }} bg-brand-ink/[.04] font-mono">
            <p class="mt-1 text-[11.5px] text-brand-ink/50">The KB number cannot be changed — counselors are matched by it. If it was typed wrong, mark this record “Tidak Aktif” and add a new record with the right number.</p>
        @else
            <input id="kb_number" name="kb_number" type="text" x-model="kb" @input="kb = kb.toUpperCase().replace(/\s/g, '')" @input.debounce.350ms="check()"
                   value="{{ $value('kb_number') }}" required maxlength="7" placeholder="KB12345" autocomplete="off" aria-describedby="kb_live" class="{{ $input }} font-mono uppercase">
            <div id="kb_live" aria-live="polite">
                <p x-show="kbBad" x-cloak class="mt-1 text-[12.5px] font-semibold text-brand-red">KB followed by 5 digits, for example KB12345.</p>
                <p x-show="found.kb" x-cloak class="mt-1 text-[12.5px] font-semibold text-brand-red">This KB number is already in the directory (<span x-text="found.kb?.name"></span>).</p>
            </div>
            @error('kb_number')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
        @endif
    </div>

    <div>
        <label for="pa_number" class="{{ $label }}">PA number</label>
        <input id="pa_number" name="pa_number" type="text" x-model="pa" @input="pa = pa.toUpperCase().replace(/\s/g, '')" @input.debounce.350ms="check()"
               value="{{ $value('pa_number') }}" required maxlength="7" placeholder="PA12345" autocomplete="off" aria-describedby="pa_live" class="{{ $input }} font-mono uppercase">
        <div id="pa_live" aria-live="polite">
            <p x-show="paBad" x-cloak class="mt-1 text-[12.5px] font-semibold text-brand-red">PA followed by 5 digits, for example PA12345.</p>
            <p x-show="found.pa.length" x-cloak class="mt-1 text-[12.5px] font-semibold text-[#7A5410]">
                Also used by <template x-for="(other, i) in found.pa" :key="other.kb_number"><span><span x-text="other.kb_number + ' (' + other.name + ')'"></span><span x-show="i < found.pa.length - 1">, </span></span></template>.
                You can still save, but check it is not a typing mistake.
            </p>
        </div>
        @error('pa_number')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label for="full_name" class="{{ $label }}">Full name <span class="font-normal text-brand-ink/50">(exactly as on the LKM register — registration compares it)</span></label>
        <input id="full_name" name="full_name" type="text" value="{{ $value('full_name') }}" required maxlength="255" class="{{ $input }}">
        <p class="mt-1 text-[11.5px] text-brand-ink/50">Name must match the counselor’s registration name exactly. Changing it affects verification.</p>
        @error('full_name')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="status" class="{{ $label }}">Status</label>
        <select id="status" name="status" required class="{{ $input }}">
            @foreach (\App\Models\LkmDirectorySnapshot::STATUSES as $key => $text)
                <option value="{{ $key }}" @selected($value('status') === $key)>{{ $text }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11.5px] text-brand-ink/50">Only “Aktif” counts as registered. Records are never deleted — change the status instead.</p>
        @error('status')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="{{ $label }}">Email <span class="font-normal text-brand-ink/50">(optional)</span></label>
        <input id="email" name="email" type="email" value="{{ $value('email') }}" maxlength="255" class="{{ $input }}">
        @error('email')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="pa_valid_from" class="{{ $label }}">PA valid from</label>
        <input id="pa_valid_from" name="pa_valid_from" type="date" x-model="from" value="{{ $value('pa_valid_from') }}" required class="{{ $input }}">
        @error('pa_valid_from')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="pa_valid_until" class="{{ $label }}">PA valid until <span class="font-normal text-brand-ink/50">(valid through the whole of this day)</span></label>
        <input id="pa_valid_until" name="pa_valid_until" type="date" x-model="until" value="{{ $value('pa_valid_until') }}" required aria-describedby="until_live" class="{{ $input }}">
        <div id="until_live" aria-live="polite">
            <p x-show="datesBad" x-cloak class="mt-1 text-[12.5px] font-semibold text-brand-red">“Valid until” cannot be before “valid from”.</p>
        </div>
        @error('pa_valid_until')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="city" class="{{ $label }}">City <span class="font-normal text-brand-ink/50">(optional)</span></label>
        <input id="city" name="city" type="text" value="{{ $value('city') }}" maxlength="100" class="{{ $input }}">
        @error('city')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="state" class="{{ $label }}">State <span class="font-normal text-brand-ink/50">(optional)</span></label>
        <input id="state" name="state" type="text" value="{{ $value('state') }}" maxlength="100" class="{{ $input }}">
        @error('state')<p class="mt-1 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
    </div>
</div>
