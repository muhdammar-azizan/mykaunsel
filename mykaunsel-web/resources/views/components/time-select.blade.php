@props(['name', 'id', 'value' => null, 'from' => null, 'to' => null, 'step' => 5, 'required' => false])

{{-- A time picker that only offers times that can be used: every :step minutes between `from` and `to` (default: the Schedule's day,
     config/counselors.php), shown as "08:05 AM" and sent as "08:05". Nothing else can be typed, so there is no "not a valid value" to fix.
     Alpine attributes go through (x-bind:required, x-bind:disabled). A value already there that is not in the list (a refused form sent back)
     is kept as the first choice instead of being lost. --}}
@php
    $minutes = fn (string $hhmm) => (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);
    $label = fn (int $m) => \Illuminate\Support\Carbon::createFromTime(intdiv($m, 60) % 24, $m % 60)->format('h:i A');
    $options = [];

    for ($m = $minutes($from ?? config('counselors.schedule.day_from', '06:00')); $m <= $minutes($to ?? config('counselors.schedule.day_to', '23:00')); $m += max(1, (int) $step)) {
        $options[sprintf('%02d:%02d', intdiv($m, 60), $m % 60)] = $label($m);
    }

    $value = is_string($value) && preg_match('/^\d{2}:\d{2}/', $value) === 1 ? substr($value, 0, 5) : null;

    if ($value !== null && ! isset($options[$value])) {
        $options = [$value => $label($minutes($value))] + $options;
    }
@endphp

<select id="{{ $id }}" name="{{ $name }}" @required($required) {{ $attributes->merge(['class' => 'mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20']) }}>
    <option value="">{{ __('Choose a time') }}</option>
    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}"{{ $value === $optionValue ? ' selected' : '' }}>{{ $optionLabel }}</option>
    @endforeach
</select>
