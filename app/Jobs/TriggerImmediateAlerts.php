<?php

namespace App\Jobs;

use App\Models\AlertRule;
use App\Models\Event;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;

class TriggerImmediateAlerts implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(AlertDispatcher $dispatcher): void
    {
        $event = Event::query()->with(['topic', 'message'])->find($this->eventId);

        if ($event === null || ($trigger = $this->trigger($event)) === null) {
            return;
        }

        $rules = AlertRule::query()
            ->where('type', 'immediate')
            ->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('topic_id')->orWhere('topic_id', $event->topic_id))
            ->whereJsonContains('config->triggers', $trigger)
            ->get();

        foreach ($rules as $rule) {
            [$subject, $body] = $this->compose($event, $trigger);

            $dispatcher->dispatch($rule, $event->topic, $subject, $body, [
                'trigger' => $trigger,
                'topic' => $event->topic->name,
                'ses_message_id' => $event->message->ses_message_id,
                'recipient' => Arr::first($event->message->recipients ?? []),
            ]);
        }
    }

    private function trigger(Event $event): ?string
    {
        return match (true) {
            $event->type === 'complaint' => 'complaint',
            $event->type === 'bounce'
                && Arr::get($event->payload, 'bounce.bounceType') === 'Permanent' => 'hard_bounce',
            default => null,
        };
    }

    /** @return array{string, string} */
    private function compose(Event $event, string $trigger): array
    {
        $topic = $event->topic->name;
        $recipient = Arr::first($event->message->recipients ?? []) ?? 'unknown recipient';

        if ($trigger === 'complaint') {
            $subject = "Complaint on {$topic}";
            $body = implode("\n", array_filter([
                "Recipient: {$recipient}",
                'Subject: '.($event->message->subject ?? '-'),
                ($type = Arr::get($event->payload, 'complaint.complaintFeedbackType')) ? "Feedback type: {$type}" : null,
                'Message: '.route('messages.show', $event->message),
            ]));

            return [$subject, $body];
        }

        $subject = "Hard bounce on {$topic}";
        $body = implode("\n", array_filter([
            "Recipient: {$recipient}",
            'Subject: '.($event->message->subject ?? '-'),
            ($diag = Arr::get($event->payload, 'bounce.bouncedRecipients.0.diagnosticCode')) ? "Diagnostic: {$diag}" : null,
            'Message: '.route('messages.show', $event->message),
        ]));

        return [$subject, $body];
    }
}
