<?php

namespace App\Models;

use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'color', 'active', 'retention_days', 'sns_topic_arn'])]
class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    /**
     * Label palette - kept to the theme's warm families, no blue/violet.
     */
    public const COLORS = [
        'mint' => '#4cb86a',
        'forest' => '#2e7d4f',
        'teal' => '#2f9e8f',
        'pear' => '#cbb042',
        'ember' => '#d97a2b',
        'coral' => '#c1133a',
        'rust' => '#8a4b2a',
        'graphite' => '#6c6960',
    ];

    protected static function booted(): void
    {
        static::creating(function (Topic $topic) {
            $topic->webhook_token ??= Str::lower(Str::random(48));
        });
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function webhookUrl(): string
    {
        return route('webhooks.ingest', $this->webhook_token);
    }

    /** Parse a "1,3" chips query value into a list of ids; anything else filters nothing. */
    public static function parseIds(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return collect(explode(',', $value))
            ->filter(fn (string $id) => ctype_digit($id))
            ->map(fn (string $id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function colorHex(): ?string
    {
        if ($this->color !== null && str_starts_with($this->color, '#')) {
            return $this->color;
        }

        return self::COLORS[$this->color] ?? null;
    }

    /** Suggested AWS resource names for the setup instructions. */
    public function awsSlug(): string
    {
        return Str::slug(Str::limit($this->name, 40, ''));
    }
}
