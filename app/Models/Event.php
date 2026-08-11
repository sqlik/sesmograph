<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

#[Fillable(['topic_id', 'type', 'occurred_at', 'payload'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const TYPES = [
        'send', 'delivery', 'bounce', 'complaint', 'open', 'click',
        'reject', 'delivery_delay', 'rendering_failure', 'subscription',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function label(): string
    {
        return match ($this->type) {
            'bounce' => trim('Bounce · '.Arr::get($this->payload, 'bounce.bounceType', ''), ' ·'),
            'delivery_delay' => 'Delivery delay',
            'rendering_failure' => 'Rendering failure',
            default => Str::headline($this->type),
        };
    }

    public function tone(): string
    {
        return match ($this->type) {
            'delivery' => 'ok',
            'bounce', 'reject', 'rendering_failure' => 'danger',
            'complaint', 'delivery_delay' => 'warn',
            default => 'neutral',
        };
    }

    /**
     * Flat key-value pairs worth showing on the timeline, per type.
     *
     * @return array<string, string>
     */
    public function details(): array
    {
        $p = $this->payload;

        $details = match ($this->type) {
            'delivery' => [
                'SMTP response' => Arr::get($p, 'delivery.smtpResponse'),
                'Processing time' => ($ms = Arr::get($p, 'delivery.processingTimeMillis')) ? "{$ms} ms" : null,
                'Reporting MTA' => Arr::get($p, 'delivery.reportingMTA'),
            ],
            'bounce' => [
                'Type' => trim(Arr::get($p, 'bounce.bounceType', '').' / '.Arr::get($p, 'bounce.bounceSubType', ''), ' /'),
                'Recipient' => Arr::get($p, 'bounce.bouncedRecipients.0.emailAddress'),
                'Diagnostic' => Arr::get($p, 'bounce.bouncedRecipients.0.diagnosticCode'),
                'Status' => Arr::get($p, 'bounce.bouncedRecipients.0.status'),
            ],
            'complaint' => [
                'Feedback type' => Arr::get($p, 'complaint.complaintFeedbackType'),
                'User agent' => Arr::get($p, 'complaint.userAgent'),
            ],
            'open' => [
                'IP address' => Arr::get($p, 'open.ipAddress'),
                'User agent' => Arr::get($p, 'open.userAgent'),
            ],
            'click' => [
                'Link' => Arr::get($p, 'click.link'),
                'IP address' => Arr::get($p, 'click.ipAddress'),
                'User agent' => Arr::get($p, 'click.userAgent'),
            ],
            'delivery_delay' => [
                'Delay type' => Arr::get($p, 'deliveryDelay.delayType'),
                'Expires' => Arr::get($p, 'deliveryDelay.expirationTime'),
                'Diagnostic' => Arr::get($p, 'deliveryDelay.delayedRecipients.0.diagnosticCode'),
            ],
            'reject' => [
                'Reason' => Arr::get($p, 'reject.reason'),
            ],
            'rendering_failure' => [
                'Error' => Arr::get($p, 'failure.errorMessage') ?? Arr::get($p, 'renderingFailure.errorMessage'),
                'Template' => Arr::get($p, 'failure.templateName') ?? Arr::get($p, 'renderingFailure.templateName'),
            ],
            default => [],
        };

        return array_filter($details, fn ($value) => $value !== null && $value !== '');
    }
}
