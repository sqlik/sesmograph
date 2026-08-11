<?php

namespace App\Console\Commands;

use App\Models\AlertRule;
use App\Models\Event;
use App\Models\Topic;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Console\Command;

class EvaluateAlerts extends Command
{
    protected $signature = 'app:evaluate-alerts';

    protected $description = 'Evaluate threshold and silence alert rules over their trailing windows';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $fired = 0;

        $rules = AlertRule::query()
            ->whereIn('type', ['threshold', 'silence'])
            ->where('enabled', true)
            ->get();

        foreach ($rules as $rule) {
            $topics = $rule->topic_id !== null
                ? Topic::query()->whereKey($rule->topic_id)->get()
                : Topic::query()->where('active', true)->get();

            foreach ($topics as $topic) {
                $fired += (int) ($rule->type === 'silence'
                    ? $this->evaluateSilence($dispatcher, $rule, $topic)
                    : $this->evaluate($dispatcher, $rule, $topic));
            }
        }

        $this->info("Evaluated {$rules->count()} rules, fired {$fired} alerts.");

        return self::SUCCESS;
    }

    /**
     * A topic that received events before but has been quiet for the
     * configured window. Topics with no events ever are skipped - they
     * are simply not connected yet.
     */
    private function evaluateSilence(AlertDispatcher $dispatcher, AlertRule $rule, Topic $topic): bool
    {
        if (! $topic->active) {
            return false;
        }

        $lastAt = $topic->events()->max('occurred_at');

        if ($lastAt === null) {
            return false;
        }

        $hours = (int) $rule->config['hours'];
        $quietHours = (int) floor(now()->diffInHours($lastAt, true));

        if ($quietHours < $hours) {
            return false;
        }

        $subject = sprintf('No events on %s for %d h', $topic->name, $quietHours);
        $body = implode("\n", [
            sprintf('%s has not received any SES event for %d hours (limit %d).', $topic->name, $quietHours, $hours),
            'Last event: '.$lastAt,
            'Check the SNS subscription and that your app still sends with the configuration set.',
            'Topic: '.route('topics.show', $topic),
        ]);

        return $dispatcher->dispatch($rule, $topic, $subject, $body, [
            'hours' => $hours,
            'quiet_hours' => $quietHours,
            'last_event_at' => (string) $lastAt,
        ]) !== null;
    }

    private function evaluate(AlertDispatcher $dispatcher, AlertRule $rule, Topic $topic): bool
    {
        $config = $rule->config;
        $since = now()->subMinutes((int) $config['window_minutes']);

        $counts = Event::query()
            ->where('topic_id', $topic->id)
            ->where('occurred_at', '>=', $since)
            ->whereIn('type', ['send', 'bounce', 'complaint'])
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $sends = (int) ($counts['send'] ?? 0);

        if ($sends < (int) $config['min_sends']) {
            return false;
        }

        $eventType = $config['metric'] === 'complaint_rate' ? 'complaint' : 'bounce';
        $rate = ($counts[$eventType] ?? 0) / $sends * 100;

        if ($rate <= (float) $config['threshold']) {
            return false;
        }

        $metricLabel = $config['metric'] === 'complaint_rate' ? 'Complaint rate' : 'Bounce rate';
        $subject = sprintf('%s %.2f%% on %s', $metricLabel, $rate, $topic->name);
        $body = implode("\n", [
            sprintf('%s over the last %d min is %.2f%% (threshold %s%%).', $metricLabel, $config['window_minutes'], $rate, $config['threshold']),
            sprintf('%d sends, %d %ss.', $sends, $counts[$eventType] ?? 0, $eventType),
            'Topic: '.route('topics.show', $topic),
        ]);

        return $dispatcher->dispatch($rule, $topic, $subject, $body, [
            'metric' => $config['metric'],
            'rate' => round($rate, 2),
            'threshold' => (float) $config['threshold'],
            'window_minutes' => (int) $config['window_minutes'],
            'sends' => $sends,
        ]) !== null;
    }
}
