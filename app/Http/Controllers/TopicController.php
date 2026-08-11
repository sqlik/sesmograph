<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Topic;
use App\Services\Stats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TopicController extends Controller
{
    public function index(): View
    {
        return view('topics.index', [
            'topics' => Topic::query()
                ->withCount('messages')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('topics.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $topic = Topic::create($this->validated($request));

        return redirect()->route('topics.setup', $topic)
            ->with('status', 'Topic created - connect AWS using the steps below');
    }

    public function show(Topic $topic, Stats $stats): View
    {
        $daily = $stats->daily($topic->id, 30);
        $days = collect($daily['days']);
        $totals = $stats->totals($topic->id, 30);
        $week = $stats->totals($topic->id, 7);

        $distribution = collect(Event::TYPES)
            ->map(fn (string $type) => [
                'label' => str($type)->headline()->toString(),
                'count' => $totals[$type],
                // Donut slice shades: green family = good outcomes, red =
                // failures, yellow = warnings, warm grays = neutral pipeline.
                'color' => match ($type) {
                    'send' => 'stone',
                    'delivery' => 'mint',
                    'open' => 'forest',
                    'click' => 'mintSoft',
                    'bounce' => 'coral',
                    'reject' => 'coralDeep',
                    'rendering_failure' => 'coralSoft',
                    'complaint' => 'pear',
                    'delivery_delay' => 'pearSoft',
                    default => 'inkFaint',
                },
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values();

        return view('topics.show', [
            'topic' => $topic,
            'hasEvents' => $topic->events()->exists(),
            'sendsHour' => $stats->sendsSince($topic->id, now()->subHour()),
            'sendsDay' => $stats->sendsSince($topic->id, now()->subDay()),
            'sendsWeek' => $week['send'],
            'sendsMonth' => $totals['send'],
            'bounceRate' => Stats::rate($totals['bounce'], $totals['send']),
            'complaintRate' => Stats::rate($totals['complaint'], $totals['send']),
            'categories' => $daily['categories'],
            'bounceSeries' => $days->map(fn (array $day) => $day['send'] > 0
                ? round($day['bounce'] / $day['send'] * 100, 2)
                : null)->all(),
            'complaintSeries' => $days->map(fn (array $day) => $day['send'] > 0
                ? round($day['complaint'] / $day['send'] * 100, 3)
                : null)->all(),
            'distribution' => $distribution,
            'recentEvents' => $topic->events()
                ->with('message')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function setup(Topic $topic): View
    {
        return view('topics.setup', ['topic' => $topic]);
    }

    public function edit(Topic $topic): View
    {
        return view('topics.edit', ['topic' => $topic]);
    }

    public function update(Request $request, Topic $topic): RedirectResponse
    {
        $topic->update($this->validated($request) + [
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('topics.show', $topic)->with('status', 'Topic updated');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        $topic->delete();

        return redirect()->route('topics.index')
            ->with('status', "Topic \"{$topic->name}\" deleted, including its messages and events");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! array_key_exists($value, Topic::COLORS) && ! preg_match('/^#[0-9a-f]{6}$/i', (string) $value)) {
                    $fail('Pick one of the swatches or a hex color.');
                }
            }],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sns_topic_arn' => ['nullable', 'string', 'max:255', 'starts_with:arn:'],
        ]);
    }
}
