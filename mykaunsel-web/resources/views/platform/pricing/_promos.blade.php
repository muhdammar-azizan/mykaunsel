@php
    /** The Promo Codes tab. $promos: a page of codes; $statuses: id => PromoCodeStatus (from PromoCode::status()). */
    $when = fn ($moment) => $moment->copy()->timezone($timezone)->format('j M Y, g:i A');
    $filterUrl = fn (?string $status) => route('platform.pricing', array_filter(['tab' => 'promos', 'status' => $status]));
@endphp

<section class="mt-6" aria-labelledby="promo-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="promo-title" class="text-[17px] font-extrabold tracking-tight">Promo codes</h2>
            <p class="max-w-[62ch] text-[13px] text-brand-ink/55">A Platform Admin applies a code when giving an organization a plan. There is no place for the public to type a code. Times are in your timezone ({{ $timezone }}).</p>
        </div>
        <button type="button" x-data @click="$dispatch('open-modal', 'add-promo')" class="inline-flex min-h-[40px] items-center rounded-full bg-brand-blue px-5 text-[13px] font-semibold text-white transition hover:bg-brand-deep">Add promo code</button>
    </div>

    <nav class="mt-4 flex flex-wrap gap-2" aria-label="Filter by status">
        <a href="{{ $filterUrl(null) }}" @if (! $statusFilter) aria-current="page" @endif
           class="inline-flex min-h-[34px] items-center gap-1.5 rounded-full border px-3.5 text-[12.5px] font-semibold transition {{ ! $statusFilter ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/15 hover:bg-brand-ink/5' }}">All <span class="opacity-80">{{ $totalCodes }}</span></a>
        @foreach (\App\Enums\PromoCodeStatus::cases() as $case)
            <a href="{{ $filterUrl($case->value) }}" @if ($statusFilter === $case) aria-current="page" @endif
               class="inline-flex min-h-[34px] items-center gap-1.5 rounded-full border px-3.5 text-[12.5px] font-semibold transition {{ $statusFilter === $case ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/15 hover:bg-brand-ink/5' }}">{{ $case->label() }} <span class="opacity-80">{{ $statusCounts[$case->value] }}</span></a>
        @endforeach
    </nav>

    <x-platform.table :headings="['Code', 'Discount', 'Applies to', 'Valid', 'Used', 'Status', '']"
        :empty="$totalCodes === 0 ? 'No promo codes yet. Use Add promo code to create the first one.' : 'No '.strtolower($statusFilter?->label() ?? '').' codes.'">
        @foreach ($promos as $promo)
            @php
                $status = $statuses[$promo->id];
                $redeemed = $promo->used_count > 0 || $promo->assignments_exists;
            @endphp
            <tr class="border-t border-brand-ink/[.07] align-middle">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2" x-data="{ copied: false }">
                        <span class="font-mono text-[13.5px] font-bold tracking-wide">{{ $promo->code }}</span>
                        <button type="button" aria-label="Copy the code {{ $promo->code }}"
                                @click="(navigator.clipboard ? navigator.clipboard.writeText(@js($promo->code)) : Promise.reject()).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})"
                                class="rounded-full border border-brand-ink/15 px-2.5 py-0.5 text-[11.5px] font-semibold transition hover:bg-brand-ink/5">
                            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                        </button>
                        <span class="sr-only" aria-live="polite" x-text="copied ? 'Copied {{ $promo->code }}' : ''"></span>
                    </div>
                </td>
                <td class="px-4 py-3 text-[13.5px] font-semibold">{{ $promo->discountLabel() }}</td>
                <td class="px-4 py-3 text-[13px] text-brand-ink/70">{{ $promo->appliesToLabel() }}</td>
                <td class="px-4 py-3 text-[12.5px] leading-snug text-brand-ink/70">{{ $when($promo->valid_from) }}<br><span class="text-brand-ink/50">to</span> {{ $when($promo->valid_until) }}</td>
                <td class="px-4 py-3 text-[13px] tabular-nums">{{ $promo->usageLabel() }}</td>
                <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $status->classes() }}">{{ $status->label() }}</span></td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" x-data @click="$dispatch('open-modal', 'edit-promo-{{ $promo->id }}')" class="inline-flex min-h-[34px] items-center rounded-full border border-brand-ink/20 px-3.5 text-[12.5px] font-semibold transition hover:bg-brand-ink/5" aria-label="Edit {{ $promo->code }}">Edit</button>
                        @if ($promo->disabled)
                            <form method="POST" action="{{ route('platform.pricing.promos.enable', $promo) }}" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" :disabled="busy" class="inline-flex min-h-[34px] items-center rounded-full border border-brand-ink/20 px-3.5 text-[12.5px] font-semibold transition hover:bg-brand-ink/5 disabled:opacity-60" aria-label="Enable {{ $promo->code }}">Enable</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('platform.pricing.promos.disable', $promo) }}" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" :disabled="busy" class="inline-flex min-h-[34px] items-center rounded-full border border-brand-ink/20 px-3.5 text-[12.5px] font-semibold transition hover:bg-brand-ink/5 disabled:opacity-60" aria-label="Disable {{ $promo->code }}">Disable</button>
                            </form>
                        @endif
                        @unless ($redeemed)
                            <button type="button" x-data @click="$dispatch('open-modal', 'delete-promo-{{ $promo->id }}')" class="inline-flex min-h-[34px] items-center rounded-full border border-brand-red/40 px-3.5 text-[12.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white" aria-label="Delete {{ $promo->code }}">Delete</button>
                        @endunless
                    </div>
                </td>
            </tr>
        @endforeach
    </x-platform.table>

    <div class="mt-4">{{ $promos->links() }}</div>
</section>

@include('platform.pricing._promo-form', ['modal' => 'add-promo', 'action' => route('platform.pricing.promos.store'), 'bag' => 'promo-new', 'promo' => null, 'redeemed' => false, 'timezone' => $timezone])

@foreach ($promos as $promo)
    @php $redeemed = $promo->used_count > 0 || $promo->assignments_exists; @endphp
    @include('platform.pricing._promo-form', ['modal' => 'edit-promo-'.$promo->id, 'action' => route('platform.pricing.promos.update', $promo), 'bag' => 'promo-'.$promo->id, 'promo' => $promo, 'redeemed' => $redeemed, 'timezone' => $timezone])

    @unless ($redeemed)
        <x-platform.confirm-modal :name="'delete-promo-'.$promo->id" :action="route('platform.pricing.promos.destroy', $promo)"
            :title="'Delete '.$promo->code.'?'" submit-label="Delete code" busy-label="Deleting…">
            @method('DELETE')
            <p class="mt-3 text-[13.5px] leading-snug text-brand-ink/75">This code has never been used, so it can be deleted. The deletion is saved in the audit log. A code that has been used can only be disabled.</p>
        </x-platform.confirm-modal>
    @endunless
@endforeach
