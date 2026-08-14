<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Message;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Local-preview data: two topics, five weeks of traffic with a weekday
 * rhythm and one bounce spike, so dashboards have something to show.
 * Not for production. Run: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $app = Topic::factory()->create(['name' => 'demo-app', 'description' => 'Transactional mail (demo data)', 'color' => 'mint']);
        $billing = Topic::factory()->create(['name' => 'demo-billing', 'description' => 'Invoices and receipts (demo data)', 'color' => 'ember']);

        $sequence = 0;

        foreach (range(34, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo);
            $weekend = in_array($day->dayOfWeekIso, [6, 7], true);
            $volume = ($weekend ? 3 : 8) + ($sequence % 4);
            // A bad list import twelve days ago: bounce rate spikes past 5%.
            $spike = $daysAgo === 12;

            foreach (range(1, $volume) as $n) {
                $sequence++;
                $topic = $sequence % 3 === 0 ? $billing : $app;
                $sentAt = $day->copy()->startOfDay()->addMinutes(fake()->numberBetween(7 * 60, 21 * 60));

                // A day in progress only has the sends that already
                // happened - the demo must never show future events.
                if ($sentAt->isFuture()) {
                    continue;
                }

                $recipient = fake()->safeEmail();

                /** @var Message $message */
                $message = Message::factory()->for($topic)->create([
                    'subject' => fake()->randomElement([
                        'Welcome to the app', 'Password reset', 'Invoice #2026-'.(100 + $sequence),
                        'Your weekly digest', 'Payment received',
                    ]),
                    'recipients' => [$recipient],
                    'status' => 'sent',
                    'status_at' => null,
                    'last_event_at' => null,
                ]);

                if ($send = $this->event($message, 'send', $sentAt, [])) {
                    $message->applyEvent('send', $send->occurred_at);
                }

                if (($spike && $n % 3 === 0) || (! $spike && $sequence % 34 === 0)) {
                    $bounce = $this->event($message, 'bounce', $sentAt->copy()->addSeconds(4), [
                        'bounce' => [
                            'bounceType' => 'Permanent',
                            'bounceSubType' => 'General',
                            'bouncedRecipients' => [[
                                'emailAddress' => $recipient,
                                'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
                                'status' => '5.1.1',
                            ]],
                        ],
                    ]);

                    if ($bounce) {
                        $message->applyEvent('bounce', $bounce->occurred_at);
                    }

                    continue;
                }

                if ($sequence % 41 === 0) {
                    $delay = $this->event($message, 'delivery_delay', $sentAt->copy()->addMinutes(6), [
                        'deliveryDelay' => [
                            'delayType' => 'MailboxFull',
                            'expirationTime' => $sentAt->copy()->addHours(8)->toIso8601String(),
                        ],
                    ]);

                    if ($delay) {
                        $message->applyEvent('delivery_delay', $delay->occurred_at);
                    }

                    continue;
                }

                $delivery = $this->event($message, 'delivery', $sentAt->copy()->addSeconds(2), [
                    'delivery' => [
                        'smtpResponse' => '250 2.0.0 OK',
                        'processingTimeMillis' => fake()->numberBetween(300, 1800),
                        'reportingMTA' => 'a8-50.smtp-out.amazonses.com',
                    ],
                ]);

                if ($delivery) {
                    $message->applyEvent('delivery', $delivery->occurred_at);
                }

                if ($sequence % 4 === 0) {
                    $open = $this->event($message, 'open', $sentAt->copy()->addMinutes(12), [
                        'open' => ['ipAddress' => fake()->ipv4(), 'userAgent' => 'Mozilla/5.0'],
                    ]);

                    if ($open) {
                        $message->applyEvent('open', $open->occurred_at);
                    }
                }

                if ($sequence % 8 === 0) {
                    $click = $this->event($message, 'click', $sentAt->copy()->addMinutes(14), [
                        'click' => ['link' => 'https://example.com/activate', 'ipAddress' => fake()->ipv4()],
                    ]);

                    if ($click) {
                        $message->applyEvent('click', $click->occurred_at);
                    }
                }

                if ($sequence % 97 === 0) {
                    $complaint = $this->event($message, 'complaint', $sentAt->copy()->addHours(2), [
                        'complaint' => [
                            'complaintFeedbackType' => 'abuse',
                            'complainedRecipients' => [['emailAddress' => $recipient]],
                        ],
                    ]);

                    if ($complaint) {
                        $message->applyEvent('complaint', $complaint->occurred_at);
                    }
                }
            }
        }

        Artisan::call('app:rebuild-aggregates');
        Artisan::call('app:rebuild-suppressed');
    }

    private function event(Message $message, string $type, \DateTimeInterface $at, array $payload): ?Event
    {
        if ($at > now()) {
            return null;
        }

        return $message->events()->create([
            'topic_id' => $message->topic_id,
            'type' => $type,
            'occurred_at' => $at,
            'payload' => $payload + ['eventType' => $type, 'mail' => ['messageId' => $message->ses_message_id]],
        ]);
    }
}
