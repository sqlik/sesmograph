<x-layouts.app :title="$topic->name">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            <x-topic-dot :topic="$topic" class="h-3 w-3" />
            <h1 class="truncate text-xl font-semibold">{{ $topic->name }}</h1>
            <x-ui.badge :tone="$topic->active ? 'ok' : 'neutral'">{{ $topic->active ? 'Active' : 'Off' }}</x-ui.badge>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('messages.index', ['topic' => $topic->id]) }}" class="inline-flex items-center justify-center rounded-full border border-edge bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                Messages
            </a>
            <a href="{{ route('topics.setup', $topic) }}" class="inline-flex items-center justify-center rounded-full border border-edge bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                AWS setup
            </a>
            <a href="{{ route('topics.edit', $topic) }}" class="inline-flex items-center justify-center rounded-full border border-edge bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                Edit
            </a>
        </div>
    </div>

    @if (! $hasEvents)
        <x-ui.card class="flex flex-col items-start gap-3">
            <p class="text-sm text-ink-soft">No events yet. They'll appear here once SES sends mail that reports to this topic.</p>
            <p class="text-xs text-ink-faint">Not connected yet? <a href="{{ route('topics.setup', $topic) }}" class="font-medium text-ink-soft underline hover:text-ink">View the AWS setup steps</a></p>
        </x-ui.card>
    @else
        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat label="Sent, last hour" :value="number_format($sendsHour)" />
            <x-ui.stat label="Sent, 24 hours" :value="number_format($sendsDay)" />
            <x-ui.stat label="Sent, 7 days" :value="number_format($sendsWeek)" />
            <x-ui.stat label="Sent, 30 days" :value="number_format($sendsMonth)" />
        </div>

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <x-ui.card>
                <h2 class="mb-1 font-medium">Bounce rate</h2>
                <p class="mb-2 text-sm text-ink-soft">
                    Daily, last 30 days.
                    30-day rate: <span class="font-medium {{ $bounceRate !== null && $bounceRate >= 5 ? 'text-danger' : 'text-ink' }}">{{ $bounceRate === null ? '-' : number_format($bounceRate, 1).'%' }}</span>
                </p>
                <div
                    data-chart="rate"
                    data-name="Bounce rate"
                    data-color="coral"
                    data-threshold="5"
                    data-series='@json($bounceSeries)'
                    data-categories='@json($categories)'
                ></div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="mb-1 font-medium">Complaint rate</h2>
                <p class="mb-2 text-sm text-ink-soft">
                    Daily, last 30 days.
                    30-day rate: <span class="font-medium {{ $complaintRate !== null && $complaintRate >= 0.1 ? 'text-danger' : 'text-ink' }}">{{ $complaintRate === null ? '-' : number_format($complaintRate, 2).'%' }}</span>
                </p>
                <div
                    data-chart="rate"
                    data-name="Complaint rate"
                    data-color="pear"
                    data-threshold="0.1"
                    data-series='@json($complaintSeries)'
                    data-categories='@json($categories)'
                ></div>
            </x-ui.card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card class="flex flex-col">
                <h2 class="mb-1 font-medium">Event types</h2>
                <p class="text-sm text-ink-soft">Last 30 days</p>
                {{-- my-auto centers the donut between subtitle and legend with equal space. --}}
                <div class="my-auto py-4" data-chart="distribution" data-series='@json($distribution)'></div>
                <div data-chart-legend class="flex flex-wrap justify-center gap-x-4 gap-y-1.5"></div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="mb-4 font-medium">Recent activity</h2>
                <x-event-feed :events="$recentEvents" />
            </x-ui.card>
        </div>
    @endif
</x-layouts.app>
