@props(['title' => null])

{{-- Mono theme shell: gray page, sidebar navigation, content on a white
     rounded sheet. Views are shared with the Hum theme untouched. --}}
@php
    $icon = fn (string $paths) => '<svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';

    $sections = [
        'Monitor' => [
            ['Dashboard', route('dashboard'), request()->routeIs('dashboard'),
                '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
            ['Messages', route('messages.index'), request()->routeIs('messages.*'),
                '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>'],
            ['Suppressed', route('suppressed.index'), request()->routeIs('suppressed.*'),
                '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>'],
        ],
        'Configure' => [
            ['Topics', route('topics.index'), request()->routeIs('topics.*'),
                '<path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.1 19.1 19"/>'],
            ['Activity', route('settings.activity'), request()->routeIs('settings.activity'),
                '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>'],
            ['Alerts', route('settings.alerts'), request()->routeIs('settings.alerts', 'settings.alert-channels.*', 'settings.alert-rules.*'),
                '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>'],
            ['API tokens', route('settings.api-tokens'), request()->routeIs('settings.api-tokens'),
                '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>'],
            ['Appearance', route('settings.appearance'), request()->routeIs('settings.appearance'),
                '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>'],
            ['Security', route('two-factor.recovery-codes'), request()->routeIs('two-factor.recovery-codes'),
                '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
        ],
    ];
@endphp

<x-layouts.base :title="$title">
    <div class="flex min-h-screen">
        <aside class="sticky top-0 flex h-screen w-60 shrink-0 flex-col gap-7 overflow-y-auto px-4 py-6">
            <a href="{{ route('dashboard') }}" class="rounded px-2 focus:outline-2 focus:outline-offset-4 focus:outline-focus">
                <x-logo />
            </a>

            <nav class="flex flex-1 flex-col gap-7 text-sm">
                @foreach ($sections as $label => $items)
                    <div>
                        <p class="mb-2 px-3 text-xs font-medium text-ink-faint">{{ $label }}</p>
                        <div class="space-y-1">
                            @foreach ($items as [$name, $url, $active, $paths])
                                <a
                                    href="{{ $url }}"
                                    class="flex items-center gap-2.5 rounded-xl px-3 py-2 font-medium focus:outline-2 focus:outline-offset-2 focus:outline-focus {{ $active ? 'bg-ink text-white' : 'text-ink-soft hover:bg-white hover:text-ink' }}"
                                    @if ($active) aria-current="page" @endif
                                >{!! $icon($paths) !!}{{ $name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="flex items-center gap-1">
                <form method="POST" action="{{ route('logout') }}" class="min-w-0 flex-1">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-ink-soft hover:bg-white hover:text-ink focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                        {!! $icon('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>') !!}Sign out
                    </button>
                </form>
                <x-theme-toggle class="shrink-0 hover:bg-white" />
            </div>
        </aside>

        <div class="min-w-0 flex-1 p-2.5 pl-0">
            <main class="min-h-full rounded-2xl bg-panel px-6 py-8 sm:px-8">
                @if (session('status'))
                    <x-ui.alert class="mb-6 max-w-xl">{{ session('status') }}</x-ui.alert>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
