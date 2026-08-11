@props(['variant' => 'primary', 'type' => 'button'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-colors focus:outline-2 focus:outline-offset-2 focus:outline-focus disabled:opacity-50';

    $variants = [
        'primary' => 'bg-accent text-ink hover:bg-accent-deep',
        'secondary' => 'border border-edge bg-surface text-ink hover:bg-edge/50',
        'danger' => 'border border-danger/30 bg-surface text-danger hover:bg-danger-soft',
    ];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $base.' '.$variants[$variant]]) }}>
    {{ $slot }}
</button>
