<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Bearer tokens for the Flutter app. The plaintext token is returned
 * once at login; only its SHA-256 hash is stored.
 */
final class AuthToken extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'token_hash', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Issues a token for the user and returns the plaintext. */
    public static function issue(User $user): string
    {
        $plain = Str::random(64);
        self::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays((int) config('condo.token_ttl_days')),
        ]);

        return $plain;
    }

    /** Resolves a non-expired token's user, or null. */
    public static function resolve(?string $plain): ?User
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return self::query()
            ->where('token_hash', hash('sha256', $plain))
            ->where('expires_at', '>', now())
            ->first()
            ?->user;
    }
}
