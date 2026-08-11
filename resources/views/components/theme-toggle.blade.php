@php
    $other = auth()->user()?->theme === 'mono' ? 'hum' : 'mono';
@endphp

{{-- One-click switch to the other theme; full picker lives on Settings -> Appearance. --}}
<form method="POST" action="{{ route('settings.appearance.update') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="theme" value="{{ $other }}">
    <button
        type="submit"
        {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-lg p-1.5 text-ink-soft hover:text-ink focus:outline-2 focus:outline-offset-2 focus:outline-focus']) }}
        title="Switch to the {{ ucfirst($other) }} theme"
        aria-label="Switch to the {{ ucfirst($other) }} theme"
    >
        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 18a6 6 0 0 0 0-12v12z" fill="currentColor" stroke="none"/></svg>
    </button>
</form>
