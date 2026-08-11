@props(['events', 'showTopic' => false])

@if ($events->isEmpty())
    <p class="text-sm text-ink-soft">No events yet.</p>
@else
    <ul class="divide-y divide-edge">
        @foreach ($events as $event)
            <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                @if ($showTopic)
                    @if ($event->topic?->colorHex())
                        {{-- Labeled topic: the dot replaces the name; click narrows the dashboard. --}}
                        <a
                            href="{{ route('dashboard', ['topics' => $event->topic_id]) }}"
                            title="{{ $event->topic->name }}"
                            class="shrink-0 rounded-full focus:outline-2 focus:outline-offset-2 focus:outline-focus"
                        ><x-topic-dot :topic="$event->topic" /></a>
                    @else
                        {{-- Keep the dot column so every row starts at the same edge. --}}
                        <span class="h-2.5 w-2.5 shrink-0" aria-hidden="true"></span>
                    @endif
                @endif
                <x-ui.badge :tone="$event->tone()" class="shrink-0">{{ $event->label() }}</x-ui.badge>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('messages.show', $event->message) }}" class="block truncate text-sm font-medium text-ink hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                        {{ $event->message->subject ?? $event->message->ses_message_id }}
                    </a>
                    <p class="truncate text-xs text-ink-faint">
                        {{ collect($event->message->recipients)->first() }}@if ($showTopic && $event->topic && ! $event->topic->colorHex()) · <a href="{{ route('dashboard', ['topics' => $event->topic_id]) }}" class="hover:text-ink hover:underline">{{ $event->topic->name }}</a>@endif
                    </p>
                </div>
                <time class="shrink-0 text-xs text-ink-faint" datetime="{{ $event->occurred_at->toIso8601String() }}">
                    {{ $event->occurred_at->format('M j, H:i') }}
                </time>
            </li>
        @endforeach
    </ul>
@endif
