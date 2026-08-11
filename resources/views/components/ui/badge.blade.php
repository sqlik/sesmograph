@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-edge/60 text-ink-soft',
        'ok' => 'bg-ok-soft text-ok',
        'danger' => 'bg-danger-soft text-danger',
        'warn' => 'bg-warn-soft text-warn',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium '.$tones[$tone]]) }}>
    {{ $slot }}
</span>
