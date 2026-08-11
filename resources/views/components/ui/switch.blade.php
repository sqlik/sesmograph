{{-- Toggle switch: a hidden checkbox that still submits like one. --}}
<label class="inline-flex cursor-pointer items-center gap-2.5 text-sm">
    <input type="checkbox" {{ $attributes->merge(['class' => 'peer sr-only']) }} />
    <span
        aria-hidden="true"
        class="relative h-5 w-9 shrink-0 rounded-full border border-edge bg-surface transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-3.5 after:w-3.5 after:rounded-full after:bg-ink after:transition-transform peer-checked:border-accent peer-checked:bg-accent peer-checked:after:translate-x-4 peer-checked:after:bg-surface peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-focus"
    ></span>
    <span>{{ $slot }}</span>
</label>
