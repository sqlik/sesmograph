{{-- Copyable code block: white pre with an icon copy button in the corner. --}}
<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ copied: false }">
    <pre class="overflow-x-auto rounded-lg border border-edge bg-surface py-2 pr-10 pl-3 text-xs leading-relaxed"><code>{{ $slot }}</code></pre>
    <x-ui.copy-icon-button
        class="absolute top-1 right-1 bg-surface"
        x-on:click="navigator.clipboard.writeText($root.querySelector('code').textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
    />
</div>
