<?php

namespace App\Services\Alerts;

use App\Models\AlertLog;
use App\Models\AlertRule;
use App\Models\Topic;
use Illuminate\Support\Facades\Log;

/**
 * Fires one alert: enforces the rule's cooldown, writes alerts_log,
 * and fans out to every enabled channel. A burst of bounces produces
 * one alert per rule+topic per cooldown window, not one per event.
 */
class AlertDispatcher
{
    public function __construct(private AlertSender $sender) {}

    public function dispatch(AlertRule $rule, Topic $topic, string $subject, string $body, array $context = []): ?AlertLog
    {
        if ($this->onCooldown($rule, $topic)) {
            return null;
        }

        $log = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'topic_id' => $topic->id,
            'subject' => $subject,
            'body' => $body,
            'context' => $context,
        ]);

        $delivery = [];

        foreach ($rule->channels()->where('enabled', true)->get() as $channel) {
            try {
                $this->sender->send($channel, $subject, $body, $context);
                $delivery[$channel->name] = 'sent';
            } catch (\Throwable $e) {
                $reason = AlertSender::redact($e->getMessage());
                $delivery[$channel->name] = 'failed: '.$reason;
                Log::warning('Alert delivery failed', [
                    'channel_id' => $channel->id,
                    'rule_id' => $rule->id,
                    'error' => $reason,
                ]);
            }
        }

        $log->update(['delivery' => $delivery]);

        return $log;
    }

    private function onCooldown(AlertRule $rule, Topic $topic): bool
    {
        if ($rule->cooldown_minutes === 0) {
            return false;
        }

        return AlertLog::query()
            ->where('alert_rule_id', $rule->id)
            ->where('topic_id', $topic->id)
            ->where('created_at', '>=', now()->subMinutes($rule->cooldown_minutes))
            ->exists();
    }
}
