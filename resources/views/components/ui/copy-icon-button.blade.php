{{-- Icon-only copy button; expects an Alpine scope with `copied` and a
     click handler passed via attributes (x-on:click). --}}
<button
    type="button"
    {{ $attributes->merge(['class' => 'rounded p-1 text-ink-faint hover:text-ink focus:outline-2 focus:outline-offset-2 focus:outline-focus']) }}
    title="Copy"
    x-bind:aria-label="copied ? 'Copied' : 'Copy'"
>
    <svg x-show="!copied" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
    <svg x-show="copied" x-cloak class="h-3.5 w-3.5 text-ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
</button>
