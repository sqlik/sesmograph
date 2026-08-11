<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['ses_message_id', 'subject', 'from_email', 'recipients', 'status', 'status_at', 'last_event_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /** Event types that change a message's delivery status. */
    public const STATUS_BY_EVENT = [
        'send' => 'sent',
        'delivery' => 'delivered',
        'bounce' => 'bounced',
        'complaint' => 'complained',
        'reject' => 'rejected',
        'delivery_delay' => 'delayed',
        'rendering_failure' => 'failed',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'status_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function content(): HasOne
    {
        // Keyed by SES message id: the same send fanned out to several
        // topics shares the one stored body.
        return $this->hasOne(MessageContent::class, 'ses_message_id', 'ses_message_id');
    }

    /**
     * Apply an incoming event to the message's status. The newest
     * status-bearing event wins; opens and clicks never change status.
     */
    public function applyEvent(string $type, \DateTimeInterface $occurredAt): void
    {
        $changes = ['last_event_at' => max($this->last_event_at ?? $occurredAt, $occurredAt)];

        $status = self::STATUS_BY_EVENT[$type] ?? null;

        if ($status !== null && ($this->status_at === null || $occurredAt >= $this->status_at)) {
            $changes['status'] = $status;
            $changes['status_at'] = $occurredAt;
        }

        $this->forceFill($changes)->save();
    }
}
