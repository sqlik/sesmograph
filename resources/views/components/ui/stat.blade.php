@props(['label', 'value', 'hint' => null, 'tone' => null])

@php
    $valueColor = match ($tone) {
        'danger' => 'text-danger',
        'warn' => 'text-warn',
        'ok' => 'text-ok',
        default => 'text-ink',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-card border border-edge bg-panel p-5 shadow-card']) }}>
    <p class="text-sm text-ink-soft">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums {{ $valueColor }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-faint">{{ $hint }}</p>
    @endif
</div>
