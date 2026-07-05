<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One in-flight "Sign in with Telegram" attempt. The row is created at
 * /auth/telegram/start, filled in when the bot receives /start <nonce>,
 * consumed (deleted) by /auth/telegram/poll, and expires after 10
 * minutes. `token` briefly holds the issued plaintext bearer token for
 * the single hand-off to the polling app.
 */
final class TelegramLogin extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['nonce', 'user_id', 'token', 'error'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
