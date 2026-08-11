<x-layouts.app title="Activity">
    <h1 class="mb-1 text-xl font-semibold">Activity</h1>
    <p class="mb-4 text-sm text-ink-soft">
        Every alert this instance sent and every API and MCP request it served.
        API entries are kept for {{ config('sesmograph.api_log_retention_days') }} days.
    </p>

    <x-settings-nav />

    <div class="space-y-6">
        <x-ui.card>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">Alerts sent</h2>
                @if ($alerts->isNotEmpty() || request()->filled('topic'))
                    <form method="GET" action="{{ route('settings.activity') }}">
                        @if (request()->filled('token'))
                            <input type="hidden" name="token" value="{{ request('token') }}">
                        @endif
                        <x-ui.select name="topic" class="w-44 text-sm" onchange="this.form.submit()" aria-label="Filter alerts by topic">
                            <option value="">All topics</option>
                            @foreach ($topics as $topic)
                                <option value="{{ $topic->id }}" @selected(request('topic') == $topic->id)>{{ $topic->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </form>
                @endif
            </div>

            @if ($alerts->isEmpty())
                <p class="text-sm text-ink-soft">
                    @if (request()->filled('topic'))
                        No alerts for this topic yet.
                    @else
                        No alerts sent yet. When a rule fires, it shows up here with its per-channel outcome.
                    @endif
                </p>
            @else
                <ul class="divide-y divide-edge">
                    @foreach ($alerts as $alert)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $alert->subject }}</p>
                                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-faint">
                                    @if ($alert->topic)
                                        <x-topic-dot :topic="$alert->topic" class="h-2 w-2" />
                                        {{ $alert->topic->name }} ·
                                    @endif
                                    {{ $alert->rule ? ucfirst($alert->rule->type).' rule' : 'Deleted rule' }}
                                </p>
                            </div>
                            <span class="flex shrink-0 flex-wrap items-center gap-1.5">
                                @forelse ($alert->delivery ?? [] as $channel => $outcome)
                                    <x-ui.badge :tone="$outcome === 'sent' ? 'ok' : 'danger'" title="{{ $outcome }}">{{ $channel }}</x-ui.badge>
                                @empty
                                    <x-ui.badge tone="neutral">no channels</x-ui.badge>
                                @endforelse
                            </span>
                            <time class="shrink-0 text-xs text-ink-faint" datetime="{{ $alert->created_at->toIso8601String() }}">
                                {{ $alert->created_at->format('M j, H:i') }}
                            </time>
                        </li>
                    @endforeach
                </ul>
                <x-ui.pagination :paginator="$alerts" />
            @endif
        </x-ui.card>

        <x-ui.card>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">API requests</h2>
                @if ($requests->isNotEmpty() || request()->filled('token'))
                    <form method="GET" action="{{ route('settings.activity') }}">
                        @if (request()->filled('topic'))
                            <input type="hidden" name="topic" value="{{ request('topic') }}">
                        @endif
                        <x-ui.select name="token" class="w-44 text-sm" onchange="this.form.submit()" aria-label="Filter requests by token">
                            <option value="">All tokens</option>
                            @foreach ($tokens as $token)
                                <option value="{{ $token->id }}" @selected(request('token') == $token->id)>{{ $token->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </form>
                @endif
            </div>

            @if ($requests->isEmpty())
                <p class="text-sm text-ink-soft">
                    @if (request()->filled('token'))
                        No requests from this token yet.
                    @else
                        No API requests yet. Calls to the REST API and the MCP server appear here.
                    @endif
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-edge text-left text-xs text-ink-faint">
                                <th class="py-2 pr-4 font-medium">Time</th>
                                <th class="py-2 pr-4 font-medium">Token</th>
                                <th class="py-2 pr-4 font-medium">Request</th>
                                <th class="py-2 pr-4 font-medium">Status</th>
                                <th class="py-2 font-medium">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-edge">
                            @foreach ($requests as $log)
                                <tr>
                                    <td class="py-2 pr-4 whitespace-nowrap text-xs text-ink-faint">{{ $log->created_at->format('M j, H:i:s') }}</td>
                                    <td class="max-w-40 truncate py-2 pr-4">{{ $log->token?->name ?? '-' }}</td>
                                    <td class="py-2 pr-4"><code class="text-xs"><span class="font-semibold">{{ $log->method }}</span> {{ $log->path }}</code></td>
                                    <td class="py-2 pr-4">
                                        <x-ui.badge :tone="$log->status < 400 ? 'ok' : ($log->status < 500 ? 'warn' : 'danger')">{{ $log->status }}</x-ui.badge>
                                    </td>
                                    <td class="py-2 text-xs text-ink-faint">{{ $log->ip }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-ui.pagination :paginator="$requests" />
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
