@props(['value'])

{{-- Copyable inline code chip: white field + icon button, check confirms. --}}
<span {{ $attributes->merge(['class' => 'inline-flex max-w-full items-center gap-0.5 align-middle']) }} x-data="{ copied: false }">
    <code class="min-w-0 truncate rounded border border-edge bg-surface px-1 py-0.5 text-xs text-ink">{{ $value }}</code>
    <x-ui.copy-icon-button
        class="shrink-0 p-0.5"
        x-on:click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)"
    />
</span>
