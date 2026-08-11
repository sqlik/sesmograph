@props(['tone' => 'ok'])

@php
    $tones = [
        'ok' => 'border-ok/25 bg-ok-soft text-ok',
        'danger' => 'border-danger/25 bg-danger-soft text-danger',
        'warn' => 'border-warn/25 bg-warn-soft text-warn',
    ];
@endphp

<div role="status" {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-2.5 text-sm font-medium '.$tones[$tone]]) }}>
    {{ $slot }}
</div>
