{{-- Collapsible "Prefer CLI?" block used in the AWS setup steps. --}}
<details class="group mt-3">
    <summary class="flex cursor-pointer list-none items-center gap-1.5 text-sm font-medium text-ink-soft hover:text-ink [&::-webkit-details-marker]:hidden">
        <svg class="h-4 w-4 transition-transform group-open:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        Prefer CLI?
    </summary>
    <x-ui.code-block class="mt-2">{{ $slot }}</x-ui.code-block>
</details>
