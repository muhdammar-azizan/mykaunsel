@props(['label' => null, 'name', 'type' => 'text', 'value' => null])

<div>
    @if ($label)
        <x-input-label for="{{ $name }}" :value="$label" />
    @endif

    <x-text-input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        class="block mt-1 w-full"
        :value="old($name, $value)"
        {{ $attributes }}
    />

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
