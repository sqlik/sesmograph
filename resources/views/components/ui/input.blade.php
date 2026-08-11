@props(['type' => 'text'])

<input
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'w-full rounded-lg border border-edge bg-surface px-3.5 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-focus focus:outline-none']) }}
>
