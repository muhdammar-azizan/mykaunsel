@php
    $me = auth()->id();
    $local = fn ($value, $format = 'j M Y, g:i A') => $value ? $value->copy()->timezone($timezone)->format($format) : null;
    $tabUrl = fn (string $key) => route('platform.admins', $key === 'active' ? [] : ['tab' => $key]);
    $inputClass = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
@endphp

<x-platform-layout title="Platform Admins">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <x-platform.page-header title="Platform Admins" subtitle="People who can sign in to this area. Everything they do is saved in the audit log." />
        <button type="button" x-data @click="$dispatch('open-modal', 'add-admin')" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Invite admin</button>
    </div>

    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    <x-platform.status-tabs
        :tabs="collect($tabs)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => $counts[$key], 'url' => $tabUrl($key)])->values()->all()"
        :active="$tab"
        label="Admin status" />

    <x-platform.table :headings="match ($tab) { 'active' => ['Admin', 'Sign-in', 'Two-factor', 'Added', ''], 'invitations' => ['Invited', 'Status', 'Sent', 'Link works until', ''], default => ['Admin', 'Removed', 'Reason', ''] }"
        :empty="match ($tab) { 'active' => 'No active Platform Admins.', 'invitations' => 'No invitations yet. Use Invite admin to send one.', default => 'Nobody has been removed.' }">
        @foreach ($invitations ?? [] as $invitation)
            @php
                $state = $invitation->displayStatus();
                $waiting = $invitation->status === \App\Enums\AdminInvitationStatus::Pending && ! $invitation->isErased();
                $chip = match ($state->value) {
                    'pending' => 'bg-brand-blue/10 text-brand-blue',
                    'accepted' => 'bg-emerald-100 text-emerald-800',
                    'expired' => 'bg-brand-yellow/20 text-[#8A6210]',
                    default => 'bg-brand-ink/10 text-brand-ink/70',
                };
            @endphp
            <tr class="border-t border-brand-ink/[.07] align-middle">
                <td class="px-4 py-3">
                    @if ($invitation->isErased())
                        <p class="text-[13px] italic text-brand-ink/55">Details erased under the retention rule</p>
                        <p class="text-[12px] text-brand-ink/45">Invitation #{{ $invitation->id }}</p>
                    @else
                        <p class="truncate text-[14px] font-semibold">{{ $invitation->name }}</p>
                        <p class="truncate text-[12.5px] text-brand-ink/60">{{ $invitation->email }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-[13px]">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $chip }}">{{ $state->label() }}</span>
                    @if ($state === \App\Enums\AdminInvitationStatus::Accepted)
                        <p class="mt-1 text-[12px] text-brand-ink/60">{{ $local($invitation->accepted_at, 'j M Y') }}</p>
                    @elseif ($invitation->status === \App\Enums\AdminInvitationStatus::Revoked)
                        <p class="mt-1 text-[12px] text-brand-ink/60">{{ $local($invitation->revoked_at, 'j M Y') }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-[13px] text-brand-ink/70">
                    {{ $local($invitation->last_sent_at) }}
                    <p class="text-[12px] text-brand-ink/55">by {{ $invitation->invitedBy?->name ?? 'a removed account' }}@if ($invitation->sent_count > 1) · sent {{ $invitation->sent_count }} times @endif</p>
                </td>
                <td class="px-4 py-3 text-[13px] text-brand-ink/70">{{ $waiting ? $local($invitation->expires_at) : '—' }}</td>
                <td class="px-4 py-3">
                    @if ($waiting)
                        <div class="flex flex-wrap justify-end gap-2">
                            <form method="POST" action="{{ route('platform.admins.invitations.resend', $invitation) }}" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" :disabled="busy" class="inline-flex min-h-[36px] items-center rounded-full border border-brand-ink/20 px-4 text-[12.5px] font-semibold transition hover:bg-brand-ink/5 disabled:opacity-60">Send again</button>
                            </form>
                            <form method="POST" action="{{ route('platform.admins.invitations.revoke', $invitation) }}" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" :disabled="busy" class="inline-flex min-h-[36px] items-center rounded-full border border-brand-red/40 px-4 text-[12.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white disabled:opacity-60">Revoke</button>
                            </form>
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach

        @foreach ($members as $member)
            @php
                $user = $member->user;
                $isMe = $user->id === $me;
                $pending = $tab === 'active' && $user->last_login_at === null;
            @endphp
            <tr class="border-t border-brand-ink/[.07] align-middle">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-blue/10 text-[12px] font-bold text-brand-blue" aria-hidden="true">{{ $user->initials }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-[14px] font-semibold">{{ $user->name }}
                                @if ($isMe)<span class="ml-1 rounded-full bg-brand-ink/10 px-2 py-0.5 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/65">You</span>@endif
                            </p>
                            <p class="truncate text-[12.5px] text-brand-ink/60">{{ $user->email }}</p>
                        </div>
                    </div>
                </td>

                @if ($tab === 'active')
                    <td class="px-4 py-3 text-[13px]">
                        @if ($pending)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-[11.5px] font-bold text-[#8A6210]">Not signed in yet</span>
                        @else
                            {{ $local($user->last_login_at) }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[13px]">
                        @if ($user->hasTwoFactorEnabled())
                            <span class="font-semibold text-emerald-700">On</span>
                        @else
                            <span class="text-brand-ink/55">Off</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[13px] text-brand-ink/70">{{ $local($member->joined_at, 'j M Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap justify-end gap-2">
                            @unless ($isMe)
                                <button type="button" x-data @click="$dispatch('open-modal', 'remove-admin-{{ $user->id }}')" class="inline-flex min-h-[36px] items-center rounded-full border border-brand-red/40 px-4 text-[12.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Remove</button>
                            @endunless
                        </div>
                    </td>
                @else
                    <td class="px-4 py-3 text-[13px] text-brand-ink/70">{{ $local($member->offboarded_at, 'j M Y, g:i A') ?? '—' }}</td>
                    <td class="max-w-[360px] px-4 py-3 text-[13px] text-brand-ink/70"><span class="line-clamp-2">{{ $member->offboard_reason ?? '—' }}</span></td>
                    <td class="px-4 py-3 text-right text-[12.5px] text-brand-ink/55">To give access again, invite them with the same email.</td>
                @endif
            </tr>
        @endforeach
    </x-platform.table>

    @if ($invitations)
        <div class="mt-4">{{ $invitations->links() }}</div>
    @endif

    <p class="mt-4 text-[13px] text-brand-ink/60">
        There must always be at least one active Platform Admin, and you cannot remove yourself. Any Platform Admin can invite and remove other admins.
        <a href="{{ route('platform.audit-log', ['category' => 'admins']) }}" class="font-semibold text-brand-blue hover:underline">See the history →</a>
    </p>

    {{-- Add admin --}}
    <x-modal name="add-admin" maxWidth="lg" :show="$errors->hasBag('invite')" focusable>
        <form method="POST" action="{{ route('platform.admins.store') }}" x-data="{ busy: false }" @submit="busy = true" class="p-6">
            @csrf
            <h2 class="text-[18px] font-extrabold tracking-tight">Invite a Platform Admin</h2>
            <ul class="mt-3 space-y-2 text-[13.5px] leading-snug text-brand-ink/75">
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> They get an email with a link that works once, for {{ config('platform-admins.invitation.expires_hours') }} hours. Nothing changes for them until they accept.</li>
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Someone with no account chooses a password. Someone who already has an account confirms with their own password (an account whose email was never verified must choose a new one).</li>
                <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> Platform Admins can read complaints and counselor documents. Only add people who should see them.</li>
            </ul>

            <label for="admin-name" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Name</label>
            <input id="admin-name" type="text" name="name" value="{{ old('name') }}" maxlength="100" required class="{{ $inputClass }}" autocomplete="off">
            @error('name', 'invite')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror

            <label for="admin-email" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Email</label>
            <input id="admin-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" required class="{{ $inputClass }}" autocomplete="off">
            @error('email', 'invite')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="$dispatch('close-modal', 'add-admin')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
                <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-show="!busy">Send invitation</span><span x-show="busy" x-cloak>Sending…</span>
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Remove (one per active admin except yourself) --}}
    @if ($tab === 'active')
        @foreach ($members as $member)
            @continue($member->user_id === $me)
            <x-platform.reason-modal :name="'remove-admin-'.$member->user_id" :action="route('platform.admins.remove', $member->user)" :bag="'remove-'.$member->user_id"
                :title="'Remove '.$member->user->name.'?'" :textarea-id="'remove-reason-'.$member->user_id"
                label="Why their access is removed (kept in the audit log)" placeholder="e.g. Left the team."
                submit-label="Remove access" busy-label="Removing…">
                <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> They are signed out on every device straight away, and emailed that their access has ended (the reason is not in that email).</li>
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Their account and everything they did stay as they are.</li>
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> You can give them access again later with "Add admin".</li>
                </ul>
            </x-platform.reason-modal>
        @endforeach
    @endif

    <x-platform.toast />
</x-platform-layout>
