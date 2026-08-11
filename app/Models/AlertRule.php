<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['topic_id', 'type', 'config', 'cooldown_minutes', 'enabled'])]
class AlertRule extends Model
{
    public const IMMEDIATE_TRIGGERS = ['hard_bounce', 'complaint'];

    public const METRICS = ['bounce_rate', 'complaint_rate'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(AlertChannel::class, 'alert_rule_channel');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AlertLog::class);
    }

    /** Human summary for the rules list, e.g. "Bounce rate > 5% over 60 min". */
    public function summary(): string
    {
        if ($this->type === 'immediate') {
            $labels = ['hard_bounce' => 'hard bounce', 'complaint' => 'complaint'];
            $triggers = array_map(fn ($t) => $labels[$t] ?? $t, $this->config['triggers'] ?? []);

            return 'Every '.implode(', every ', $triggers);
        }

        if ($this->type === 'silence') {
            return sprintf('No events for %d h', $this->config['hours']);
        }

        $metric = $this->config['metric'] === 'complaint_rate' ? 'Complaint rate' : 'Bounce rate';

        return sprintf(
            '%s > %s%% over %d min (min %d sends)',
            $metric,
            rtrim(rtrim(number_format((float) $this->config['threshold'], 2), '0'), '.'),
            $this->config['window_minutes'],
            $this->config['min_sends'],
        );
    }
}
