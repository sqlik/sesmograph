<x-layouts.app title="API tokens">
    <h1 class="mb-1 text-xl font-semibold">API tokens</h1>
    <p class="mb-4 text-sm text-ink-soft">
        Tokens authenticate your services against the content ingest endpoint,
        the read-only API, and the MCP server.
    </p>

    <x-settings-nav />

    @if (session('plainToken'))
        <x-ui.card class="mb-6">
            <p class="mb-2 text-sm font-medium">Copy this token now - it is shown only once.</p>
            <x-ui.copy-line :value="session('plainToken')" />
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="flex flex-col gap-6">
            <x-ui.card>
                <h2 class="mb-3 text-sm font-semibold">Tokens</h2>

                @if ($tokens->isEmpty())
                    <p class="mb-4 text-sm text-ink-soft">No tokens yet. Create one per service so you can revoke them independently.</p>
                @else
                    <ul class="mb-4 space-y-2">
                        @foreach ($tokens as $token)
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-edge bg-surface px-4 py-2.5">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{{ $token->name }}</span>
                                    <span class="block text-xs text-ink-faint">
                                        {{ $token->last_used_at ? 'Last used '.$token->last_used_at->diffForHumans() : 'Never used' }}
                                    </span>
                                </span>
                                <form method="POST" action="{{ route('settings.api-tokens.destroy', $token) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-danger hover:underline">Revoke</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('settings.api-tokens.store') }}" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <x-ui.label for="name">Token name</x-ui.label>
                        <x-ui.input id="name" name="name" placeholder="acme-app" required />
                        <x-ui.error for="name" />
                    </div>
                    <x-ui.button type="submit" variant="primary">Create token</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card class="flex-1">
                <h2 class="mb-1 text-sm font-semibold">MCP server</h2>
                <p class="mb-3 text-sm text-ink-soft">
                    Read-only Streamable HTTP endpoint for Claude Code, Cursor, and other MCP
                    clients. Tools: search events, message timeline with bounce diagnostics,
                    delivery stats, and suppressed-address checks.
                </p>

                <x-ui.copy-line class="mb-4" :value="url('/mcp')" />

                <p class="mb-1 text-xs font-medium text-ink-soft">Claude Code</p>
                <x-ui.code-block class="mb-3">claude mcp add --transport http sesmograph {{ url('/mcp') }} \
  --header "Authorization: Bearer &lt;token&gt;"</x-ui.code-block>

                <p class="mb-1 text-xs font-medium text-ink-soft">Cursor and other clients (mcp.json)</p>
                <x-ui.code-block>{
  "mcpServers": {
    "sesmograph": {
      "url": "{{ url('/mcp') }}",
      "headers": { "Authorization": "Bearer &lt;token&gt;" }
    }
  }
}</x-ui.code-block>
            </x-ui.card>
        </div>

        <x-ui.card>
            <h2 class="mb-1 text-sm font-semibold">REST API</h2>
            <p class="mb-3 text-sm text-ink-soft">
                Base URL <code class="rounded border border-edge bg-surface px-1 py-0.5 text-xs">{{ url('/api/v1') }}</code>,
                authenticated with <code class="rounded border border-edge bg-surface px-1 py-0.5 text-xs">Authorization: Bearer &lt;token&gt;</code>.
                Read endpoints are limited to 120 requests per minute.
            </p>

            <ul class="mb-4 divide-y divide-edge">
                <li class="py-2.5">
                    <code class="text-xs"><span class="font-semibold">GET</span> /api/v1/events</code>
                    <p class="mt-0.5 text-sm text-ink-soft">Search events, newest first. Filters: <code class="text-xs">q</code> (recipient, subject, sender, or SES message ID), <code class="text-xs">topic</code>, <code class="text-xs">type</code>, <code class="text-xs">from</code>/<code class="text-xs">to</code> dates, <code class="text-xs">page</code>.</p>
                </li>
                <li class="py-2.5">
                    <code class="text-xs"><span class="font-semibold">GET</span> /api/v1/messages/{sesMessageId}</code>
                    <p class="mt-0.5 text-sm text-ink-soft">One message's full timeline, oldest first, including SMTP bounce diagnostics.</p>
                </li>
                <li class="py-2.5">
                    <code class="text-xs"><span class="font-semibold">GET</span> /api/v1/stats</code>
                    <p class="mt-0.5 text-sm text-ink-soft">Daily counts, totals, bounce and complaint rates from the aggregates. Params: <code class="text-xs">topic</code>, <code class="text-xs">from</code>, <code class="text-xs">to</code> (defaults to the last 30 days).</p>
                </li>
                <li class="py-2.5">
                    <code class="text-xs"><span class="font-semibold">GET</span> /api/v1/suppressed</code>
                    <p class="mt-0.5 text-sm text-ink-soft">With <code class="text-xs">?address=</code>: is this address safe to send to. Without it: the full list, filterable by <code class="text-xs">topic</code> and <code class="text-xs">reason</code>.</p>
                </li>
                <li class="py-2.5">
                    <code class="text-xs"><span class="font-semibold">POST</span> /api/v1/messages/{sesMessageId}/content</code>
                    <p class="mt-0.5 text-sm text-ink-soft">Push the sent email's body (<code class="text-xs">html</code>, <code class="text-xs">text</code>) so it shows on the message page. Kept 30 days.</p>
                </li>
                <li class="py-2.5">
                    <code class="text-xs"><span class="font-semibold">GET</span> /api/v1/health</code>
                    <p class="mt-0.5 text-sm text-ink-soft">For uptime monitors: returns <code class="text-xs">ok</code> plus the age of the newest event. Not recorded in the activity log.</p>
                </li>
            </ul>

            <p class="mb-1 text-xs font-medium text-ink-soft">Example: check an address before sending</p>
            <x-ui.code-block class="mb-3">curl -H "Authorization: Bearer &lt;token&gt;" \
  "{{ url('/api/v1/suppressed') }}?address=user@example.com"</x-ui.code-block>

            <p class="mb-1 text-xs font-medium text-ink-soft">Example: bounce rate for one topic</p>
            <x-ui.code-block>curl -H "Authorization: Bearer &lt;token&gt;" \
  "{{ url('/api/v1/stats') }}?topic=my-app&from={{ today()->subDays(6)->toDateString() }}"</x-ui.code-block>
        </x-ui.card>
    </div>
</x-layouts.app>
