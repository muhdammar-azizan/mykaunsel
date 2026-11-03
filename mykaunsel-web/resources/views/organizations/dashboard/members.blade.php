<x-portal-layout>
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Members</h1>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">The people of {{ $organization->name }} who can book your counselors: who is waiting, how people join, and who belongs.</p>

    @php
        // The words an admin can copy to the people who should use a code: where to type it is not obvious to someone new.
        $howTo = fn ($code) => "Hello,\n\n{$organization->name} now offers counselling through MyKaunsel. To use it:\n"
            ."1. Install the MyKaunsel app and create an account.\n"
            ."2. Confirm your e-mail address.\n"
            ."3. Open Account, then Your organization, and enter this code: {$code->display()}\n"
            ."4. Wait for us to approve you. You will get an e-mail.";
        $when = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value, 'UTC')->timezone($timezone)->format('j M Y, g:i A') : null;
    @endphp

    <form method="GET" action="{{ route('org.members') }}" class="mt-5 flex flex-wrap items-center gap-3" role="search">
        <label for="member-search" class="sr-only">Search members by name or e-mail</label>
        <input id="member-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search people by name or e-mail" class="w-full max-w-sm rounded-md border border-brand-ink/20 px-4 min-h-[44px] text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20">
        <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white">Search</button>
        @if ($search !== '')<a href="{{ route('org.members') }}" class="text-[14px] font-semibold text-brand-blue hover:underline">Clear</a>@endif
    </form>
    <div class="mt-5">
        <div class="space-y-5">
            @if (session('status'))
                <div role="status" class="rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div role="alert" class="rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 font-semibold text-brand-red">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 font-semibold text-brand-red">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- People who used a reference code. They get nothing until someone here says yes. --}}
            <section aria-labelledby="waiting" class="rounded-xl border border-[#E4E2DE] bg-white p-6">
                <h3 id="waiting" class="text-[20px] font-bold tracking-tight text-brand-ink">Waiting for your approval ({{ $waiting->count() }})</h3>
                <p class="mt-1 text-sm text-brand-ink/65">These people used one of your reference codes. Approve only people who work for you. Until then they can not book your counselors.</p>

                @forelse ($waiting as $row)
                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-[#EEEDEA] pt-4">
                        <div class="min-w-[200px] flex-1">
                            <p class="font-medium text-brand-ink">{{ $row->user->name }}</p>
                            <p class="text-sm text-brand-ink/65">{{ $row->user->email }} · asked on {{ $when($row->joined_at) }}</p>
                        </div>
                        <form method="POST" action="{{ route('org.members.approve', $row->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-[40px] items-center rounded-md bg-brand-blue px-4 text-sm font-semibold text-white transition hover:bg-brand-deep">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('org.members.decline', $row->id) }}" data-confirm-title="Decline this request?" data-confirm="The person will be told by e-mail." data-confirm-button="Decline request" data-confirm-cancel="Go back">
                            @csrf
                            <button type="submit" class="inline-flex min-h-[40px] items-center rounded-md border border-brand-red/50 px-4 text-sm font-semibold text-brand-red transition hover:bg-brand-red/[.06]">Decline</button>
                        </form>
                    </div>
                @empty
                    @if ($search !== '')
                        <p class="mt-4 text-sm text-brand-ink/55">Nobody waiting matches your search.</p>
                    @else
                        <x-empty-state-waiting title="Nobody is waiting." text="New requests will show up here for you to approve." />
                    @endif
                @endforelse
            </section>

            {{-- Invitations by e-mail: active at once when accepted in the app --}}
            <section aria-labelledby="invitations" class="rounded-xl border border-[#E4E2DE] bg-white p-6">
                <h3 id="invitations" class="text-[20px] font-bold tracking-tight text-brand-ink">Invite by e-mail</h3>
                <p class="mt-1 text-sm text-brand-ink/65">The person gets an e-mail, signs in to the MyKaunsel app with that address and accepts. They join at once, without your approval, because you asked them.</p>

                <form method="POST" action="{{ route('org.invitations.store') }}" class="mt-4 flex flex-wrap items-end gap-4">
                    @csrf
                    <div class="min-w-[260px] flex-1">
                        <label for="invite_email" class="block text-sm font-medium text-brand-ink/70">E-mail address</label>
                        <input id="invite_email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="mt-1 block w-full min-h-[44px] rounded-md border border-brand-ink/20 px-3 text-sm">
                    </div>
                    <button type="submit" class="inline-flex min-h-[40px] items-center rounded-md bg-brand-blue px-4 text-sm font-semibold text-white transition hover:bg-brand-deep">Send invitation</button>
                </form>

                @if ($invitations->isNotEmpty())
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <caption class="sr-only">Invitations</caption>
                            <thead class="text-brand-ink/55">
                                <tr>
                                    <th scope="col" class="py-2 pr-4 font-medium">E-mail</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">Sent</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">State</th>
                                    <th scope="col" class="py-2 font-medium"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EEEDEA]">
                                @foreach ($invitations as $invitation)
                                    <tr>
                                        <td class="py-2 pr-4">{{ $invitation->email }}</td>
                                        <td class="py-2 pr-4">{{ $when($invitation->created_at) }}</td>
                                        <td class="py-2 pr-4">{{ $invitation->state() }}</td>
                                        <td class="py-2">
                                            @if ($invitation->isOpen())
                                                <form method="POST" action="{{ route('org.invitations.revoke', $invitation->id) }}" data-confirm-title="Cancel this invitation?" data-confirm="" data-confirm-button="Cancel invitation" data-confirm-cancel="Keep it">
                                                    @csrf
                                                    <button type="submit" class="text-sm font-semibold text-brand-red hover:underline">Cancel</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Reference codes --}}
            <section aria-labelledby="codes" class="rounded-xl border border-[#E4E2DE] bg-white p-6">
                <h3 id="codes" class="text-[20px] font-bold tracking-tight text-brand-ink">Reference codes</h3>
                <p class="mt-1 text-sm text-brand-ink/65">For employees who only have an ordinary e-mail address (not your company's). They enter the code in the MyKaunsel app and you approve them above.</p>

                <form method="POST" action="{{ route('org.codes.store') }}" class="mt-4 flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label for="expires" class="block text-sm font-medium text-brand-ink/70">Works for</label>
                        <select id="expires" name="expires" class="mt-1 rounded-md border-brand-ink/20 text-sm">
                            @foreach ($expiryChoices as $value => $label)
                                <option value="{{ $value }}" @selected(old('expires', '30') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="max_uses" class="block text-sm font-medium text-brand-ink/70">Number of people (optional)</label>
                        <input id="max_uses" name="max_uses" type="number" min="1" max="1000" value="{{ old('max_uses') }}" class="mt-1 w-40 rounded-md border-brand-ink/20 text-sm">
                    </div>
                    <button type="submit" class="inline-flex min-h-[40px] items-center rounded-md bg-brand-blue px-4 text-sm font-semibold text-white transition hover:bg-brand-deep">Create a code</button>
                </form>

                @if ($codes->isNotEmpty())
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <caption class="sr-only">Reference codes</caption>
                            <thead class="text-brand-ink/55">
                                <tr>
                                    <th scope="col" class="py-2 pr-4 font-medium">Code</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">Used</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">Works until</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">State</th>
                                    <th scope="col" class="py-2 font-medium"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EEEDEA]">
                                @foreach ($codes as $code)
                                    @php
                                        $state = $code->revoked_at ? 'Switched off' : ($code->isUsable() ? 'On' : ($code->expires_at && $code->expires_at->isPast() ? 'Expired' : 'All places used'));
                                    @endphp
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-base font-semibold tracking-wider">{{ $code->display() }}</td>
                                        <td class="py-2 pr-4">{{ $code->used_count }}{{ $code->max_uses ? ' of '.$code->max_uses : '' }}</td>
                                        <td class="py-2 pr-4">{{ $code->expires_at ? $when($code->expires_at) : 'No expiry' }}</td>
                                        <td class="py-2 pr-4">{{ $state }}</td>
                                        <td class="py-2">
                                            @if ($code->isUsable())
                                                <form method="POST" action="{{ route('org.codes.revoke', $code->id) }}" data-confirm-title="Switch this code off?" data-confirm="Nobody new can use it." data-confirm-button="Switch off" data-confirm-cancel="Keep it on">
                                                    @csrf
                                                    <button type="submit" class="text-sm font-semibold text-brand-red hover:underline">Switch off</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($code->isUsable())
                                        <tr>
                                            <td colspan="5" class="pb-3">
                                                <details>
                                                    <summary class="cursor-pointer text-sm font-semibold text-brand-blue">Message to send with this code</summary>
                                                    <label for="howto-{{ $code->id }}" class="sr-only">Message to send with this code</label>
                                                    <textarea id="howto-{{ $code->id }}" readonly rows="6" class="mt-2 block w-full rounded-md border-brand-ink/20 text-sm" onclick="this.select()">{{ $howTo($code) }}</textarea>
                                                </details>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-4 text-sm text-brand-ink/55">No codes yet.</p>
                @endif
            </section>

            {{-- People who belong --}}
            <section aria-labelledby="members" class="rounded-xl border border-[#E4E2DE] bg-white p-6">
                <h3 id="members" class="text-[20px] font-bold tracking-tight text-brand-ink">Members ({{ $members->total() }})</h3>

                @forelse ($members as $row)
                    <div class="mt-3 flex flex-wrap items-baseline gap-x-4 border-t border-[#EEEDEA] pt-3">
                        <p class="font-medium text-brand-ink">{{ $row->user->name }}</p>
                        <p class="text-sm text-brand-ink/65">{{ $row->user->email }}</p>
                        <p class="text-sm text-brand-ink/55">{{ ucfirst($row->role->value) }} · since {{ $when($row->joined_at) }}</p>
                        <form method="POST" action="{{ route('org.members.remove', $row->id) }}" class="ml-auto" data-confirm-title="Remove this person?" data-confirm="Their later sessions with your counselors are cancelled, except those in the next {{ \App\Services\MembershipEnding::GRACE_DAYS }} days. They are told by e-mail." data-confirm-button="Remove person" data-confirm-cancel="Keep">
                            @csrf
                            <button type="submit" class="text-sm font-semibold text-brand-red hover:underline">Remove</button>
                        </form>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-brand-ink/55">{{ $search !== '' ? 'Nobody matches your search.' : 'Nobody has joined yet.' }}</p>
                @endforelse

                @if ($members->hasPages())
                    <div class="mt-4">{{ $members->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-portal-layout>
