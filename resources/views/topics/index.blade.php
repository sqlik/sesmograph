<x-layouts.app title="Topics">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Topics</h1>
        <a href="{{ route('topics.create') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-4 py-2 text-sm font-medium text-ink hover:bg-accent-deep focus:outline-2 focus:outline-offset-2 focus:outline-focus">
            Add topic
        </a>
    </div>

    @if ($topics->isEmpty())
        <x-ui.card>
            <p class="text-sm text-ink-soft">No topics yet. A topic receives SES events for one of your services.</p>
        </x-ui.card>
    @else
        <ul class="space-y-3">
            @foreach ($topics as $topic)
                <li>
                    <a href="{{ route('topics.show', $topic) }}" class="flex items-center justify-between gap-4 rounded-card border border-edge bg-panel px-5 py-4 hover:border-ink-faint focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                        <span class="min-w-0">
                            <span class="flex items-center gap-2 font-medium"><x-topic-dot :topic="$topic" /><span class="truncate">{{ $topic->name }}</span></span>
                            @if ($topic->description)
                                <span class="mt-0.5 block truncate text-sm text-ink-soft">{{ $topic->description }}</span>
                            @endif
                        </span>
                        <span class="flex shrink-0 items-center gap-3 text-sm text-ink-soft">
                            <span>{{ number_format($topic->messages_count) }} {{ $topic->messages_count === 1 ? 'message' : 'messages' }}</span>
                            <x-ui.badge :tone="$topic->active ? 'ok' : 'neutral'">{{ $topic->active ? 'Active' : 'Off' }}</x-ui.badge>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
