@props(['topic'])

{{-- Colored label dot; renders nothing for unlabeled topics. --}}
@if ($topic->colorHex())
    <span {{ $attributes->merge(['class' => 'inline-block h-2.5 w-2.5 shrink-0 rounded-full']) }} style="background: {{ $topic->colorHex() }}" aria-hidden="true"></span>
@endif
