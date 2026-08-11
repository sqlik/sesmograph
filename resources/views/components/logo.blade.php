@props(['size' => 'base'])

{{-- Wordmark with the envelope trace: a calm line that enters the envelope,
     breaks into the flap's vertex inside, and pulses once on each side. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 select-none']) }}>
    <svg
        class="{{ $size === 'lg' ? 'h-6 w-12' : 'h-5 w-10' }} shrink-0 text-ink"
        viewBox="0 0 48 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <rect x="12" y="4" width="24" height="16" rx="4" />
        <path d="M1 10h3.2l.9-2.8 1.2 5.6.9-2.8H18l6 4.5 6-4.5h9.8l.9-2.8 1.2 5.6.9-2.8H47" />
    </svg>
    <span class="{{ $size === 'lg' ? 'text-xl' : 'text-lg' }} font-semibold tracking-tight lowercase">sesmograph</span>
</span>
