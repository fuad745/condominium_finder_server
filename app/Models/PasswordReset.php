<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 6-digit email reset codes: hashed, 15-minute expiry, 5 attempts. */
final class PasswordReset extends Model
{
    public const UPDATED_AT = null;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['user_id', 'code_hash', 'expires_at', 'used', 'attempts'];

    protected function casts(): array
    {
        return [
            'used' => 'boolean',
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
