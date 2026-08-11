<x-layouts.app title="Suppressed addresses">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Suppressed addresses</h1>
        @if ($addresses->isNotEmpty())
            <a href="{{ route('suppressed.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-full border border-edge bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                Export CSV
            </a>
        @endif
    </div>

    <x-topic-chips :topics="$topics" :selected="$selectedTopicIds" route="suppressed.index" />

    <form method="GET" action="{{ route('suppressed.index') }}" class="mb-6 rounded-card border border-edge bg-panel p-4">
        @if (request()->filled('topics'))
            <input type="hidden" name="topics" value="{{ request('topics') }}">
        @endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <x-ui.label for="q">Address</x-ui.label>
                <x-ui.input id="q" name="q" :value="request('q')" placeholder="user@example.com" />
            </div>
            <div>
                <x-ui.label for="reason">Reason</x-ui.label>
                <x-ui.select id="reason" name="reason">
                    <option value="">Any</option>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason }}" @selected(request('reason') === $reason)>{{ ucfirst($reason) }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="flex items-end gap-3">
                <x-ui.button type="submit" variant="primary">Filter</x-ui.button>
                @if (request()->hasAny(['q', 'topic', 'topics', 'reason']))
                    <a href="{{ route('suppressed.index') }}" class="py-2 text-sm font-medium text-ink-soft hover:text-ink">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($addresses->isEmpty())
        <x-ui.card>
            <p class="text-sm text-ink-soft">
                @if (request()->hasAny(['q', 'topic', 'topics', 'reason']))
                    Nothing matches these filters.
                @else
                    No suppressed addresses. Recipients land here automatically after a hard bounce or a complaint.
                @endif
            </p>
        </x-ui.card>
    @else
        <ul class="space-y-2">
            @foreach ($addresses as $address)
                <li class="flex items-center justify-between gap-4 rounded-card border border-edge bg-panel px-5 py-3.5">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">{{ $address->address }}</span>
                        <span class="mt-0.5 block truncate text-sm text-ink-soft">
                            {{ $address->last_diagnostic ?? ($address->reason === 'complaint' ? 'Recipient marked the message as spam' : 'No diagnostic recorded') }}
                        </span>
                    </span>
                    <span class="flex shrink-0 items-center gap-3 text-sm text-ink-soft">
                        <span class="hidden sm:inline">{{ $address->topic->name }}</span>
                        <x-ui.badge :tone="$address->reason === 'bounce' ? 'danger' : 'warn'">
                            {{ ucfirst($address->reason) }}{{ $address->hits > 1 ? ' × '.$address->hits : '' }}
                        </x-ui.badge>
                        <time datetime="{{ $address->last_event_at->toIso8601String() }}" class="tabular-nums">
                            {{ $address->last_event_at->format('M j, H:i') }}
                        </time>
                        <form method="POST" action="{{ route('suppressed.destroy', $address) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-danger hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                                Remove
                            </button>
                        </form>
                    </span>
                </li>
            @endforeach
        </ul>

        <x-ui.pagination :paginator="$addresses" />
    @endif
</x-layouts.app>
