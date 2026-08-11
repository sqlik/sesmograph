<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ses_message_id', 'message_id', 'html', 'text', 'html_bytes'])]
class MessageContent extends Model
{
    public const UPDATED_AT = null;

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    protected function html(): Attribute
    {
        return $this->compressed('html_gz');
    }

    protected function text(): Attribute
    {
        return $this->compressed('text_gz');
    }

    /** Transparent gzip+base64 storage for large bodies. */
    private function compressed(string $column): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes[$column])
                ? gzdecode(base64_decode($attributes[$column]))
                : null,
            set: fn (?string $value) => [
                $column => $value === null ? null : base64_encode(gzencode($value, 6)),
            ],
        )->shouldCache();
    }
}
