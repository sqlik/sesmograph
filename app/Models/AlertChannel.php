<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'type', 'config', 'enabled'])]
class AlertChannel extends Model
{
    public const TYPES = ['smtp', 'telegram', 'pushover', 'webhook'];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'enabled' => 'boolean',
        ];
    }

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(AlertRule::class, 'alert_rule_channel');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'smtp' => 'Email (SMTP)',
            'telegram' => 'Telegram',
            'pushover' => 'Pushover',
            'webhook' => 'Webhook',
            default => ucfirst((string) $this->type),
        };
    }

    /** One-line description of where this channel points, for lists. */
    public function target(): string
    {
        return match ($this->type) {
            'smtp' => $this->config['to_address'] ?? '',
            'telegram' => 'chat '.($this->config['chat_id'] ?? ''),
            'pushover' => 'user '.substr($this->config['user_key'] ?? '', 0, 6).'...',
            'webhook' => $this->config['url'] ?? '',
            default => '',
        };
    }
}
