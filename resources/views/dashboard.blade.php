<x-layouts.app title="Dashboard">
    <h1 class="mb-6 text-xl font-semibold">Dashboard</h1>

    @if ($topics->isEmpty())
        <x-ui.card class="flex flex-col items-start gap-3">
            <p class="text-sm text-ink-soft">No topics yet. A topic receives SES events for one of your services.</p>
            <a href="{{ route('topics.create') }}" class="inline-flex items-center justify-center rounded-full bg-accent px-4 py-2 text-sm font-medium text-ink hover:bg-accent-deep focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                Add topic
            </a>
        </x-ui.card>
    @else
        <x-topic-chips :topics="$topics" :selected="$selectedTopicIds" route="dashboard" />

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat label="Sent, 24 hours" :value="number_format($sends24h)" />
            <x-ui.stat label="Sent, 7 days" :value="number_format($totals['send'])" />
            <x-ui.stat
                label="Bounce rate, 7 days"
                :value="$bounceRate === null ? '-' : number_format($bounceRate, 1).'%'"
                :tone="$bounceRate !== null && $bounceRate >= 5 ? 'danger' : null"
                hint="AWS limit 5%"
            />
            <x-ui.stat
                label="Complaint rate, 7 days"
                :value="$complaintRate === null ? '-' : number_format($complaintRate, 2).'%'"
                :tone="$complaintRate !== null && $complaintRate >= 0.1 ? 'danger' : null"
                hint="AWS limit 0.1%"
            />
        </div>

        <x-ui.card class="mb-6">
            <h2 class="mb-1 font-medium">Volume, last 30 days</h2>
            <p class="mb-2 text-sm text-ink-soft">Sends per day across all topics, by outcome.</p>
            <div
                data-chart="volume"
                data-series='@json($volumeSeries)'
                data-categories='@json($volumeCategories)'
            ></div>
        </x-ui.card>

        <div class="grid gap-6 lg:grid-cols-5">
            <x-ui.card class="lg:col-span-3">
                <h2 class="mb-4 font-medium">Recent activity</h2>
                <x-event-feed :events="$recentEvents" :show-topic="true" />
            </x-ui.card>

            <div class="lg:col-span-2">
                <div class="space-y-3">
                    @foreach ($topics as $topic)
                        @php $week = $weekByTopic[$topic->id] ?? null; @endphp
                        <a href="{{ route('topics.show', $topic) }}" class="block rounded-card border border-edge bg-panel p-4 hover:border-ink-faint focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                            <div class="mb-1 flex items-center justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2 font-medium"><x-topic-dot :topic="$topic" /><span class="truncate">{{ $topic->name }}</span></span>
                                <span class="flex shrink-0 items-center gap-1.5">
                                    @if ($silentHours = $silentByTopic[$topic->id] ?? null)
                                        <x-ui.badge tone="warn" title="No events for {{ $silentHours }} hours - check the SNS subscription">Silent {{ $silentHours }} h</x-ui.badge>
                                    @endif
                                    <x-ui.badge :tone="$topic->active ? 'ok' : 'neutral'">{{ $topic->active ? 'Active' : 'Off' }}</x-ui.badge>
                                </span>
                            </div>
                            <p class="text-sm text-ink-soft">
                                {{ number_format($week->sends ?? 0) }} sent, 7 days
                                @if (($week->sends ?? 0) > 0)
                                    · {{ number_format($week->bounces / $week->sends * 100, 1) }}% bounce
                                @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
