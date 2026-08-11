<nav class="mb-6 flex items-center gap-1 text-sm" aria-label="Settings">
    <a
        href="{{ route('settings.activity') }}"
        class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('settings.activity') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }}"
    >Activity</a>
    <a
        href="{{ route('settings.alerts') }}"
        class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('settings.alerts', 'settings.alert-channels.*', 'settings.alert-rules.*') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }}"
    >Alerts</a>
    <a
        href="{{ route('settings.api-tokens') }}"
        class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('settings.api-tokens') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }}"
    >API tokens</a>
    <a
        href="{{ route('settings.appearance') }}"
        class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('settings.appearance') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }}"
    >Appearance</a>
    <a
        href="{{ route('two-factor.recovery-codes') }}"
        class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('two-factor.recovery-codes') ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink' }}"
    >Security</a>
</nav>
