{{-- Label color swatches + custom color. Expects $current (color name, hex, or null). --}}
@php
    $isCustom = $current !== null && str_starts_with($current, '#');
@endphp
<div x-data="{ custom: @js($isCustom ? strtolower($current) : '#b0623a'), customActive: @js($isCustom) }" x-on:change="customActive = $refs.customRadio.checked">
    <x-ui.label>Label color <span class="font-normal text-ink-faint">(optional)</span></x-ui.label>
    <div class="mt-1.5 flex flex-wrap items-center gap-2">
        <label class="cursor-pointer" title="No label">
            <input type="radio" name="color" value="" class="peer sr-only" @checked($current === null)>
            <span class="flex h-7 w-7 items-center justify-center rounded-full border border-edge bg-surface text-ink-faint peer-checked:outline-2 peer-checked:outline-offset-2 peer-checked:outline-ink peer-focus-visible:ring-2 peer-focus-visible:ring-focus peer-focus-visible:ring-offset-2">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/></svg>
            </span>
        </label>
        @foreach (\App\Models\Topic::COLORS as $name => $hex)
            <label class="cursor-pointer" title="{{ ucfirst($name) }}">
                <input type="radio" name="color" value="{{ $name }}" class="peer sr-only" @checked($current === $name)>
                <span class="block h-7 w-7 rounded-full border border-edge peer-checked:outline-2 peer-checked:outline-offset-2 peer-checked:outline-ink peer-focus-visible:ring-2 peer-focus-visible:ring-focus peer-focus-visible:ring-offset-2" style="background: {{ $hex }}"></span>
            </label>
        @endforeach
        {{-- Custom color: the swatch is a native color input; picking one checks the hidden radio that carries the hex. --}}
        <span class="relative inline-flex">
            <input type="radio" name="color" x-ref="customRadio" x-bind:value="custom" value="{{ $isCustom ? strtolower($current) : '' }}" class="peer sr-only" @checked($isCustom) aria-label="Custom color">
            <input
                type="color"
                x-model="custom"
                x-on:input="$refs.customRadio.checked = true; customActive = true"
                x-on:click="$refs.customRadio.checked = true; customActive = true"
                class="h-7 w-7 cursor-pointer rounded-full border border-edge p-0 [&::-moz-color-swatch]:rounded-full [&::-moz-color-swatch]:border-0 [&::-webkit-color-swatch]:rounded-full [&::-webkit-color-swatch]:border-0 [&::-webkit-color-swatch-wrapper]:p-0.5"
                title="Custom color"
            >
            {{-- Color-wheel face signals "pick any color" until a custom color is chosen. --}}
            <span
                class="pointer-events-none absolute inset-0 rounded-full border border-edge"
                x-show="!customActive"
                style="background: conic-gradient(from 0deg, #c1133a, #d97a2b, #cbb042, #4cb86a, #2f9e8f, #6c6960, #c1133a)"
            ></span>
            <span class="pointer-events-none absolute inset-0 rounded-full peer-checked:outline-2 peer-checked:outline-offset-2 peer-checked:outline-ink"></span>
        </span>
    </div>
    <x-ui.error for="color" />
</div>
