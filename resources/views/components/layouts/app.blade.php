@props(['title' => null])

@if (auth()->user()?->theme === 'mono')
    <x-layouts.mono :title="$title">{{ $slot }}</x-layouts.mono>
@else
<x-layouts.base :title="$title">
    <header class="border-b border-edge bg-canvas">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
            <a href="{{ route('dashboard') }}" class="rounded focus:outline-2 focus:outline-offset-4 focus:outline-focus">
                <x-logo />
            </a>
            <nav class="flex items-center gap-1 text-sm">
                <a
                    href="{{ route('dashboard') }}"
                    class="rounded-lg px-3 py-1.5 font-medium {{ request()->routeIs('dashboard') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }} focus:outline-2 focus:outline-offset-2 focus:outline-focus"
                >Dashboard</a>
                <a
                    href="{{ route('messages.index') }}"
                    class="rounded-lg px-3 py-1.5 font-medium {{ request()->routeIs('messages.*') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }} focus:outline-2 focus:outline-offset-2 focus:outline-focus"
                >Messages</a>
                <a
                    href="{{ route('suppressed.index') }}"
                    class="rounded-lg px-3 py-1.5 font-medium {{ request()->routeIs('suppressed.*') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }} focus:outline-2 focus:outline-offset-2 focus:outline-focus"
                >Suppressed</a>
                <a
                    href="{{ route('topics.index') }}"
                    class="rounded-lg px-3 py-1.5 font-medium {{ request()->routeIs('topics.*') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }} focus:outline-2 focus:outline-offset-2 focus:outline-focus"
                >Topics</a>
                <a
                    href="{{ route('two-factor.recovery-codes') }}"
                    class="rounded-lg px-3 py-1.5 font-medium {{ request()->routeIs('two-factor.recovery-codes', 'settings.*') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }} focus:outline-2 focus:outline-offset-2 focus:outline-focus"
                >Settings</a>
                <form method="POST" action="{{ route('logout') }}" class="ml-2">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-1.5 font-medium text-ink-soft hover:text-ink focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                        Sign out
                    </button>
                </form>
                <x-theme-toggle class="ml-1" />
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        @if (session('status'))
            <x-ui.alert class="mb-6 max-w-xl">{{ session('status') }}</x-ui.alert>
        @endif

        {{ $slot }}
    </main>
</x-layouts.base>
@endif
