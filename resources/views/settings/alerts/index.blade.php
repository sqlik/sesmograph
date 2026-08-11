<x-layouts.app title="Alerts">
    <div>
        <h1 class="mb-1 text-xl font-semibold">Alerts</h1>
        <p class="mb-4 text-sm text-ink-soft">
            Channels deliver alerts; rules decide when to fire them. Alerts never go through SES.
        </p>

        <x-settings-nav />

        <section class="mb-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-medium">Channels</h2>
                <a href="{{ route('settings.alert-channels.create') }}" class="inline-flex items-center justify-center rounded-full bg-accent px-4 py-2 text-sm font-medium text-ink hover:bg-accent-deep focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                    Add channel
                </a>
            </div>

            <x-ui.card>
                @if ($channels->isEmpty())
                    <p class="text-sm text-ink-soft">No channels yet. Add one, then attach it to a rule.</p>
                @else
                    <ul class="divide-y divide-edge">
                        @foreach ($channels as $channel)
                            <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-sm font-medium">{{ $channel->name }}</span>
                                        @unless ($channel->enabled)
                                            <x-ui.badge>Off</x-ui.badge>
                                        @endunless
                                    </div>
                                    <p class="truncate text-xs text-ink-faint">{{ $channel->typeLabel() }} · {{ $channel->target() }}</p>
                                </div>
                                <form method="POST" action="{{ route('settings.alert-channels.test', $channel) }}">
                                    @csrf
                                    <button type="submit" class="text-sm font-medium text-ink-soft hover:text-ink hover:underline">Send test</button>
                                </form>
                                <a href="{{ route('settings.alert-channels.edit', $channel) }}" class="text-sm font-medium text-ink-soft hover:text-ink hover:underline">Edit</a>
                                <form method="POST" action="{{ route('settings.alert-channels.destroy', $channel) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-danger hover:underline">Delete</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </section>

        <section class="mb-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-medium">Rules</h2>
                <a href="{{ route('settings.alert-rules.create') }}" class="inline-flex items-center justify-center rounded-full bg-accent px-4 py-2 text-sm font-medium text-ink hover:bg-accent-deep focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                    Add rule
                </a>
            </div>

            <x-ui.card>
                @if ($rules->isEmpty())
                    <p class="text-sm text-ink-soft">No rules yet. A rule watches a topic and fires its channels.</p>
                @else
                    <ul class="divide-y divide-edge">
                        @foreach ($rules as $rule)
                            <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-sm font-medium">{{ $rule->summary() }}</span>
                                        @unless ($rule->enabled)
                                            <x-ui.badge>Off</x-ui.badge>
                                        @endunless
                                    </div>
                                    <p class="truncate text-xs text-ink-faint">
                                        {{ $rule->topic?->name ?? 'All topics' }}
                                        · {{ $rule->channels->pluck('name')->join(', ') ?: 'no channels' }}
                                        · cooldown {{ $rule->cooldown_minutes }} min
                                    </p>
                                </div>
                                <a href="{{ route('settings.alert-rules.edit', $rule) }}" class="text-sm font-medium text-ink-soft hover:text-ink hover:underline">Edit</a>
                                <form method="POST" action="{{ route('settings.alert-rules.destroy', $rule) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-danger hover:underline">Delete</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </section>

        <section>
            <h2 class="mb-3 font-medium">Recent alerts</h2>
            <x-ui.card>
                @if ($logs->isEmpty())
                    <p class="text-sm text-ink-soft">No alerts fired yet.</p>
                @else
                    <ul class="divide-y divide-edge">
                        @foreach ($logs as $log)
                            <li class="py-2.5 first:pt-0 last:pb-0">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="min-w-0 truncate text-sm font-medium">{{ $log->subject }}</span>
                                    <time class="shrink-0 text-xs text-ink-faint" datetime="{{ $log->created_at->toIso8601String() }}">
                                        {{ $log->created_at->format('M j, H:i') }}
                                    </time>
                                </div>
                                @if ($log->delivery)
                                    <p class="mt-0.5 truncate text-xs text-ink-faint">
                                        @foreach ($log->delivery as $channelName => $outcome)
                                            {{ $channelName }}: {{ $outcome === 'sent' ? 'sent' : $outcome }}@if (! $loop->last) · @endif
                                        @endforeach
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </section>
    </div>
</x-layouts.app>
