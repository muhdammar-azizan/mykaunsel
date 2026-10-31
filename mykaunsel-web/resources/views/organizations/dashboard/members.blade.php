<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Ahli Organisasi') }}: {{ $organization->name }}</h2>
    </x-slot>

    @php
        // The words an admin can copy to the people who should use a code: where to type it is not obvious to someone new.
        $howTo = fn ($code) => "Hello,\n\n{$organization->name} now offers counselling through MyKaunsel. To use it:\n"
            ."1. Install the MyKaunsel app and create an account.\n"
            ."2. Confirm your e-mail address.\n"
            ."3. Open Account, then Your organization, and enter this code: {$code->display()}\n"
            ."4. Wait for us to approve you. You will get an e-mail.";
        $when = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value, 'UTC')->timezone($timezone)->format('j M Y, g:i A') : null;
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))
                <div role="status" class="rounded-md bg-green-50 p-4 text-green-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div role="alert" class="rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="rounded-md bg-red-50 p-4 text-red-800">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- People who used a reference code. They get nothing until someone here says yes. --}}
            <section aria-labelledby="waiting" class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 id="waiting" class="text-lg font-semibold text-gray-900">Waiting for your approval ({{ $waiting->count() }})</h3>
                <p class="mt-1 text-sm text-gray-600">These people used one of your reference codes. Approve only people who work for you. Until then they can not book your counselors.</p>

                @forelse ($waiting as $row)
                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                        <div class="min-w-[200px] flex-1">
                            <p class="font-medium text-gray-900">{{ $row->user->name }}</p>
                            <p class="text-sm text-gray-600">{{ $row->user->email }} · asked on {{ $when($row->joined_at) }}</p>
                        </div>
                        <form method="POST" action="{{ route('org.members.approve', $row->id) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('org.members.decline', $row->id) }}" onsubmit="return confirm('Decline this request? The person will be told by e-mail.');">
                            @csrf
                            <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Decline</button>
                        </form>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-gray-500">Nobody is waiting.</p>
                @endforelse
            </section>

            {{-- Invitations by e-mail: active at once when accepted in the app --}}
            <section aria-labelledby="invitations" class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 id="invitations" class="text-lg font-semibold text-gray-900">Invite by e-mail</h3>
                <p class="mt-1 text-sm text-gray-600">The person gets an e-mail, signs in to the MyKaunsel app with that address and accepts. They join at once, without your approval, because you asked them.</p>

                <form method="POST" action="{{ route('org.invitations.store') }}" class="mt-4 flex flex-wrap items-end gap-4">
                    @csrf
                    <div class="min-w-[260px] flex-1">
                        <label for="invite_email" class="block text-sm font-medium text-gray-700">E-mail address</label>
                        <input id="invite_email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Send invitation</button>
                </form>

                @if ($invitations->isNotEmpty())
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <caption class="sr-only">Invitations</caption>
                            <thead class="text-gray-500">
                                <tr>
                                    <th scope="col" class="py-2 pr-4 font-medium">E-mail</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">Sent</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">State</th>
                                    <th scope="col" class="py-2 font-medium"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($invitations as $invitation)
                                    <tr>
                                        <td class="py-2 pr-4">{{ $invitation->email }}</td>
                                        <td class="py-2 pr-4">{{ $when($invitation->created_at) }}</td>
                                        <td class="py-2 pr-4">{{ $invitation->state() }}</td>
                                        <td class="py-2">
                                            @if ($invitation->isOpen())
                                                <form method="POST" action="{{ route('org.invitations.revoke', $invitation->id) }}" onsubmit="return confirm('Cancel this invitation?');">
                                                    @csrf
                                                    <button type="submit" class="text-sm font-semibold text-red-700 hover:underline">Cancel</button>
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
            <section aria-labelledby="codes" class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 id="codes" class="text-lg font-semibold text-gray-900">Reference codes</h3>
                <p class="mt-1 text-sm text-gray-600">For employees who only have an ordinary e-mail address (not your company's). They enter the code in the MyKaunsel app and you approve them above.</p>

                <form method="POST" action="{{ route('org.codes.store') }}" class="mt-4 flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label for="expires" class="block text-sm font-medium text-gray-700">Works for</label>
                        <select id="expires" name="expires" class="mt-1 rounded-md border-gray-300 text-sm">
                            @foreach ($expiryChoices as $value => $label)
                                <option value="{{ $value }}" @selected(old('expires', '30') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="max_uses" class="block text-sm font-medium text-gray-700">Number of people (optional)</label>
                        <input id="max_uses" name="max_uses" type="number" min="1" max="1000" value="{{ old('max_uses') }}" class="mt-1 w-40 rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Create a code</button>
                </form>

                @if ($codes->isNotEmpty())
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <caption class="sr-only">Reference codes</caption>
                            <thead class="text-gray-500">
                                <tr>
                                    <th scope="col" class="py-2 pr-4 font-medium">Code</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">Used</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">Works until</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">State</th>
                                    <th scope="col" class="py-2 font-medium"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
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
                                                <form method="POST" action="{{ route('org.codes.revoke', $code->id) }}" onsubmit="return confirm('Switch this code off? Nobody new can use it.');">
                                                    @csrf
                                                    <button type="submit" class="text-sm font-semibold text-red-700 hover:underline">Switch off</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($code->isUsable())
                                        <tr>
                                            <td colspan="5" class="pb-3">
                                                <details>
                                                    <summary class="cursor-pointer text-sm font-semibold text-indigo-700">Message to send with this code</summary>
                                                    <label for="howto-{{ $code->id }}" class="sr-only">Message to send with this code</label>
                                                    <textarea id="howto-{{ $code->id }}" readonly rows="6" class="mt-2 block w-full rounded-md border-gray-300 text-sm" onclick="this.select()">{{ $howTo($code) }}</textarea>
                                                </details>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-500">No codes yet.</p>
                @endif
            </section>

            {{-- People who belong --}}
            <section aria-labelledby="members" class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 id="members" class="text-lg font-semibold text-gray-900">Members ({{ $members->count() }})</h3>

                @forelse ($members as $row)
                    <div class="mt-3 flex flex-wrap items-baseline gap-x-4 border-t border-gray-100 pt-3">
                        <p class="font-medium text-gray-900">{{ $row->user->name }}</p>
                        <p class="text-sm text-gray-600">{{ $row->user->email }}</p>
                        <p class="text-sm text-gray-500">{{ ucfirst($row->role->value) }} · since {{ $when($row->joined_at) }}</p>
                        <form method="POST" action="{{ route('org.members.remove', $row->id) }}" class="ml-auto" onsubmit="return confirm('Remove this person? Their later sessions with your counselors are cancelled, except those in the next {{ \App\Services\MembershipEnding::GRACE_DAYS }} days. They are told by e-mail.');">
                            @csrf
                            <button type="submit" class="text-sm font-semibold text-red-700 hover:underline">Remove</button>
                        </form>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-gray-500">Nobody has joined yet.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>
