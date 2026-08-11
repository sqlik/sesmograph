{{-- Same size and shape as x-ui.input; native select with a custom chevron. --}}
<div class="relative">
    <select {{ $attributes->merge(['class' => 'w-full appearance-none rounded-lg border border-edge bg-surface py-2 pl-3.5 pr-9 text-sm text-ink focus:border-focus focus:outline-none']) }}>
        {{ $slot }}
    </select>
    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
</div>
