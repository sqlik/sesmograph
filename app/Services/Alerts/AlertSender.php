<?php

namespace App\Services\Alerts;

use App\Models\AlertChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers one alert to one channel. Throws on failure - callers decide
 * whether that means a failed test button or a logged delivery error.
 */
class AlertSender
{
    /**
     * Strip secrets that HTTP client exceptions echo back - chiefly the
     * Telegram bot token, which the API carries in the request URL.
     */
    public static function redact(string $message): string
    {
        return (string) preg_replace('#bot\d+:[A-Za-z0-9_-]+#', 'bot[REDACTED]', $message);
    }

    public function send(AlertChannel $channel, string $subject, string $body, array $context = []): void
    {
        match ($channel->type) {
            'smtp' => $this->smtp($channel->config, $subject, $body),
            'telegram' => $this->telegram($channel->config, $subject, $body),
            'pushover' => $this->pushover($channel->config, $subject, $body),
            'webhook' => $this->webhook($channel->config, $subject, $body, $context),
        };
    }

    /** Independent SMTP on purpose: the alert must arrive even when SES is down. */
    private function smtp(array $config, string $subject, string $body): void
    {
        $mailer = Mail::build([
            'transport' => 'smtp',
            'host' => $config['host'],
            'port' => (int) $config['port'],
            'username' => $config['username'] ?: null,
            'password' => $config['password'] ?: null,
            'timeout' => 15,
        ]);

        $mailer->raw($body, function ($message) use ($config, $subject) {
            $message->from($config['from_address'], 'sesmograph')
                ->to($config['to_address'])
                ->subject($subject);
        });
    }

    private function telegram(array $config, string $subject, string $body): void
    {
        Http::timeout(10)
            ->post("https://api.telegram.org/bot{$config['bot_token']}/sendMessage", [
                'chat_id' => $config['chat_id'],
                'text' => "{$subject}\n\n{$body}",
                'disable_web_page_preview' => true,
            ])
            ->throw();
    }

    private function pushover(array $config, string $subject, string $body): void
    {
        Http::timeout(10)
            ->post('https://api.pushover.net/1/messages.json', [
                'token' => $config['app_token'],
                'user' => $config['user_key'],
                'title' => $subject,
                'message' => $body,
            ])
            ->throw();
    }

    /** POST JSON with an HMAC-SHA256 signature over the exact raw body. */
    private function webhook(array $config, string $subject, string $body, array $context): void
    {
        $payload = json_encode([
            'event' => 'alert',
            'subject' => $subject,
            'body' => $body,
            'context' => $context ?: (object) [],
            'sent_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES);

        $request = Http::timeout(10)->withBody($payload, 'application/json');

        if (! empty($config['secret'])) {
            $request = $request->withHeaders([
                'X-Sesmograph-Signature' => 'sha256='.hash_hmac('sha256', $payload, $config['secret']),
            ]);
        }

        $request->post($config['url'])->throw();
    }
}
