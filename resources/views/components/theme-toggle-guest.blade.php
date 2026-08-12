{{-- Guest counterpart of x-theme-toggle: no account to store the choice in,
     so it flips data-theme live and remembers it in the plain `theme` cookie
     (excluded from cookie encryption; the base layout reads it back). --}}
<button
    type="button"
    x-data
    @click="
        const next = document.documentElement.dataset.theme === 'mono' ? 'hum' : 'mono';
        document.documentElement.dataset.theme = next;
        document.cookie = `theme=${next};path=/;max-age=31536000;SameSite=Lax`;
    "
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-lg p-1.5 text-ink-soft hover:text-ink focus:outline-2 focus:outline-offset-2 focus:outline-focus']) }}
    title="Switch theme"
    aria-label="Switch theme"
>
    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 18a6 6 0 0 0 0-12v12z" fill="currentColor" stroke="none"/></svg>
</button>
