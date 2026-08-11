<?php

namespace App\Http\Controllers;

use App\Models\DailyAggregate;
use App\Models\Event;
use App\Models\Topic;
use App\Services\Stats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Stats $stats): View
    {
        $topics = Topic::query()->withCount('messages')->orderBy('name')->get();

        // ?topics=1,3 narrows every number on the page to those topics.
        $selected = collect(Topic::parseIds($request->query('topics')))
            ->intersect($topics->pluck('id'))
            ->values();

        $topicIds = $selected->isEmpty() ? null : $selected->all();

        // Active topics that have gone quiet - a broken SNS subscription
        // fails silently, so surface the gap instead of showing zeros.
        $threshold = (int) config('sesmograph.silent_topic_hours');
        $silentByTopic = Event::query()
            ->selectRaw('topic_id, max(occurred_at) as last_at')
            ->groupBy('topic_id')
            ->pluck('last_at', 'topic_id')
            ->map(fn ($last) => (int) floor(now()->diffInHours($last, true)))
            ->filter(fn (int $hours, int $topicId) => $hours >= $threshold
                && $topics->firstWhere('id', $topicId)?->active)
            ->all();

        $totals = $stats->totals($topicIds, 7);
        $daily = $stats->daily($topicIds, 30);

        $volume = collect($daily['days'])->map(function (array $day) {
            $other = max(0, $day['send'] - $day['delivery'] - $day['bounce']);

            return ['delivered' => $day['delivery'], 'bounced' => $day['bounce'], 'other' => $other];
        });

        $weekAggregates = DailyAggregate::query()
            ->where('date', '>=', today()->subDays(6)->toDateString())
            ->selectRaw('topic_id, sum(send_count) as sends, sum(bounce_count) as bounces')
            ->groupBy('topic_id')
            ->get()
            ->keyBy('topic_id');

        return view('dashboard', [
            'topics' => $topics,
            'selectedTopicIds' => $selected->all(),
            'silentByTopic' => $silentByTopic,
            'weekByTopic' => $weekAggregates,
            'totals' => $totals,
            'sends24h' => $stats->sendsSince($topicIds, now()->subDay()),
            'deliveredRate' => Stats::rate($totals['delivery'], $totals['send']),
            'bounceRate' => Stats::rate($totals['bounce'], $totals['send']),
            'complaintRate' => Stats::rate($totals['complaint'], $totals['send']),
            'volumeCategories' => $daily['categories'],
            'volumeSeries' => [
                ['name' => 'Delivered', 'data' => $volume->pluck('delivered')->all()],
                ['name' => 'Bounced', 'data' => $volume->pluck('bounced')->all()],
                ['name' => 'Other', 'data' => $volume->pluck('other')->all()],
            ],
            'recentEvents' => Event::query()
                ->with(['message', 'topic'])
                ->when($topicIds !== null, fn ($query) => $query->whereIn('topic_id', $topicIds))
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get(),
        ]);
    }
}
