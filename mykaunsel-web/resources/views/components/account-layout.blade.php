@props(['title' => 'Account'])

{{-- The Account pages are shown inside the shell of the area the person is working in (see App\Support\AccountArea). --}}
@if (\App\Support\AccountArea::prefix() === 'portal')
    <x-portal-layout :title="$title">{{ $slot }}</x-portal-layout>
@else
    <x-platform-layout :title="$title">{{ $slot }}</x-platform-layout>
@endif
