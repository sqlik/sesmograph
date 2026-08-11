<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['name', 'token_hash', 'last_used_at'])]
class ApiToken extends Model
{
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Create a token and return [model, plainText]. The plain value is
     * shown exactly once; only its SHA-256 lands in the database.
     */
    public static function issue(string $name): array
    {
        $plain = 'smg_'.Str::random(40);

        $token = self::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
        ]);

        return [$token, $plain];
    }

    public static function findByPlainText(?string $plain): ?self
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return self::query()->where('token_hash', hash('sha256', $plain))->first();
    }
}
