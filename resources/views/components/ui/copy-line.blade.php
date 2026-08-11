@props(['value'])

{{-- Wide single-line copyable field: white field with the icon inside. --}}
<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ copied: false }">
    <code class="block truncate rounded-lg border border-edge bg-surface py-2 pr-10 pl-3 text-xs">{{ $value }}</code>
    <x-ui.copy-icon-button
        class="absolute top-1/2 right-1 -translate-y-1/2"
        x-on:click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)"
    />
</div>
