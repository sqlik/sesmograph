<x-layouts.app :title="$message->subject ?? 'Message'">
    <div class="mb-6">
        <a href="{{ route('messages.index') }}" class="text-sm font-medium text-ink-soft hover:text-ink"><- Messages</a>
    </div>

    <div class="max-w-3xl space-y-6">
        <x-ui.card>
            <div class="mb-4 flex items-start justify-between gap-4">
                <h1 class="text-lg font-semibold">{{ $message->subject ?? 'No subject' }}</h1>
                <x-ui.badge :tone="match ($message->status) {
                    'delivered' => 'ok',
                    'bounced', 'rejected', 'failed' => 'danger',
                    'complained', 'delayed' => 'warn',
                    default => 'neutral',
                }">{{ ucfirst($message->status) }}</x-ui.badge>
            </div>

            <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex gap-2">
                    <dt class="w-16 shrink-0 text-ink-faint">From</dt>
                    <dd class="min-w-0 truncate">{{ $message->from_email ?? '-' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-16 shrink-0 text-ink-faint">To</dt>
                    <dd class="min-w-0 truncate">{{ implode(', ', $message->recipients ?? []) ?: '-' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-16 shrink-0 text-ink-faint">Topic</dt>
                    <dd><a href="{{ route('topics.show', $message->topic) }}" class="font-medium hover:underline">{{ $message->topic->name }}</a></dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-16 shrink-0 text-ink-faint">SES ID</dt>
                    <dd class="min-w-0 truncate"><code class="text-xs">{{ $message->ses_message_id }}</code></dd>
                </div>
            </dl>
        </x-ui.card>

        @if ($content !== null)
            <section x-data="{ tab: '{{ $content->html !== null ? 'preview' : 'text' }}' }">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold">Content</h2>
                    <div class="flex items-center gap-1 text-sm" role="tablist">
                        @if ($content->html !== null)
                            <button type="button" role="tab" x-on:click="tab = 'preview'" :class="tab === 'preview' ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink'" class="rounded-full px-3 py-1.5 font-medium">Preview</button>
                        @endif
                        @if ($content->text !== null)
                            <button type="button" role="tab" x-on:click="tab = 'text'" :class="tab === 'text' ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink'" class="rounded-full px-3 py-1.5 font-medium">Plain text</button>
                        @endif
                        @if ($content->html !== null)
                            <button type="button" role="tab" x-on:click="tab = 'source'" :class="tab === 'source' ? 'bg-panel text-ink' : 'text-ink-soft hover:text-ink'" class="rounded-full px-3 py-1.5 font-medium">Source</button>
                        @endif
                    </div>
                </div>

                @if ($content->html !== null)
                    <div x-show="tab === 'preview'">
                        @unless (request()->boolean('images'))
                            <p class="mb-2 text-xs text-ink-faint">
                                Remote images are blocked so this view does not trigger open tracking.
                                <a href="{{ route('messages.show', [$message, 'images' => 1]) }}" class="font-medium text-ink-soft hover:text-ink">Load remote images</a>
                            </p>
                        @endunless
                        <iframe
                            srcdoc="{{ $previewHtml }}"
                            sandbox
                            referrerpolicy="no-referrer"
                            title="Email preview"
                            class="h-[32rem] w-full rounded-card border border-edge bg-surface"
                        ></iframe>
                    </div>
                @endif

                @if ($content->text !== null)
                    <pre x-show="tab === 'text'" x-cloak class="max-h-[32rem] overflow-auto whitespace-pre-wrap rounded-card border border-edge bg-surface px-4 py-3 text-sm leading-relaxed">{{ $content->text }}</pre>
                @endif

                @if ($content->html !== null)
                    <pre x-show="tab === 'source'" x-cloak class="max-h-[32rem] overflow-auto rounded-card border border-edge bg-surface px-4 py-3 text-xs leading-relaxed">{{ $content->html }}</pre>
                @endif
            </section>
        @endif

        <section>
            <h2 class="mb-4 text-lg font-semibold">Timeline</h2>

            <ol class="relative space-y-6 border-l border-edge pl-6">
                @foreach ($events as $event)
                    @php
                        $dot = match ($event->tone()) {
                            'ok' => 'bg-ok',
                            'danger' => 'bg-danger',
                            'warn' => 'bg-warn',
                            default => 'bg-ink-faint',
                        };
                    @endphp
                    <li class="relative">
                        <span class="absolute -left-[1.815rem] top-1.5 h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                        <div class="flex items-baseline justify-between gap-4">
                            <h3 class="font-medium">{{ $event->label() }}</h3>
                            <time datetime="{{ $event->occurred_at->toIso8601String() }}" class="shrink-0 text-sm tabular-nums text-ink-soft">
                                {{ $event->occurred_at->format('M j, Y H:i:s T') }}
                            </time>
                        </div>

                        @if ($details = $event->details())
                            <dl class="mt-2 space-y-1 text-sm">
                                @foreach ($details as $key => $value)
                                    <div class="flex gap-2">
                                        <dt class="w-32 shrink-0 text-ink-faint">{{ $key }}</dt>
                                        <dd class="min-w-0 break-words text-ink-soft">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        <details class="group mt-2">
                            <summary class="flex cursor-pointer list-none items-center gap-1.5 text-xs font-medium text-ink-faint hover:text-ink [&::-webkit-details-marker]:hidden">
                                <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                Raw event
                            </summary>
                            <pre class="mt-2 max-h-96 overflow-auto rounded-lg border border-edge bg-surface px-3 py-2 text-xs leading-relaxed">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.app>
