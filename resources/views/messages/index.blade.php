<x-layouts.app title="Messages">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Messages</h1>
        @if ($messages->isNotEmpty())
            <a href="{{ route('messages.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-full border border-edge bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                Export CSV
            </a>
        @endif
    </div>

    <x-topic-chips :topics="$topics" :selected="$selectedTopicIds" route="messages.index" />

    <form method="GET" action="{{ route('messages.index') }}" class="mb-6 rounded-card border border-edge bg-panel p-4">
        @if (request()->filled('topics'))
            <input type="hidden" name="topics" value="{{ request('topics') }}">
        @endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <x-ui.label for="q">Search</x-ui.label>
                <x-ui.input id="q" name="q" :value="request('q')" placeholder="Recipient, subject, or message ID" />
            </div>
            <div>
                <x-ui.label for="status">Status</x-ui.label>
                <x-ui.select id="status" name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div>
                <x-ui.label for="type">Event type</x-ui.label>
                <x-ui.select id="type" name="type">
                    <option value="">Any</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div>
                <x-ui.label for="from">From date</x-ui.label>
                <x-ui.input id="from" name="from" type="date" :value="request('from')" />
            </div>
            <div>
                <x-ui.label for="to">To date</x-ui.label>
                <x-ui.input id="to" name="to" type="date" :value="request('to')" />
            </div>
            <div class="flex items-end gap-3">
                <x-ui.button type="submit" variant="primary">Filter</x-ui.button>
                @if (request()->hasAny(['q', 'topic', 'topics', 'status', 'type', 'from', 'to']))
                    <a href="{{ route('messages.index') }}" class="py-2 text-sm font-medium text-ink-soft hover:text-ink">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($messages->isEmpty())
        <x-ui.card>
            <p class="text-sm text-ink-soft">
                @if (request()->hasAny(['q', 'topic', 'status', 'type', 'from', 'to']))
                    Nothing matches these filters.
                @else
                    No messages yet. They appear as soon as a connected topic receives SES events.
                @endif
            </p>
        </x-ui.card>
    @else
        {{-- Same row anatomy as the dashboard feed: dot column, badge, subject + meta, time. --}}
        <x-ui.card>
            <ul class="divide-y divide-edge">
                @foreach ($messages as $message)
                    <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                        @if ($message->topic?->colorHex())
                            <a
                                href="{{ route('messages.index', array_merge(request()->except(['page', 'topics']), ['topics' => $message->topic_id])) }}"
                                title="{{ $message->topic->name }}"
                                class="shrink-0 rounded-full focus:outline-2 focus:outline-offset-2 focus:outline-focus"
                            ><x-topic-dot :topic="$message->topic" /></a>
                        @else
                            <span class="h-2.5 w-2.5 shrink-0" aria-hidden="true"></span>
                        @endif
                        <x-ui.badge class="shrink-0" :tone="match ($message->status) {
                            'delivered' => 'ok',
                            'bounced', 'rejected', 'failed' => 'danger',
                            'complained', 'delayed' => 'warn',
                            default => 'neutral',
                        }">{{ ucfirst($message->status) }}</x-ui.badge>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('messages.show', $message) }}" class="block truncate text-sm font-medium text-ink hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                                {{ $message->subject ?? 'No subject' }}
                            </a>
                            <p class="truncate text-xs text-ink-faint">
                                {{ collect($message->recipients)->first() ?? 'unknown' }}@if ($message->topic && ! $message->topic->colorHex()) · <a href="{{ route('messages.index', array_merge(request()->except(['page', 'topics']), ['topics' => $message->topic_id])) }}" class="hover:text-ink hover:underline">{{ $message->topic->name }}</a>@endif
                            </p>
                        </div>
                        <time class="shrink-0 text-xs text-ink-faint" datetime="{{ $message->last_event_at?->toIso8601String() }}">
                            {{ $message->last_event_at?->format('M j, H:i') }}
                        </time>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <x-ui.pagination :paginator="$messages" />
    @endif
</x-layouts.app>
