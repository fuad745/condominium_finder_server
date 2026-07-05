<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * App users and admins. Passwords live in `password_hash` (bcrypt) and
 * there is no updated_at column — rows carry created_at only.
 */
final class User extends Authenticatable implements FilamentUser, HasName
{
    public const UPDATED_AT = null;

    /** Points thresholds — keep in sync with backend/api/src/helpers.php. */
    public const TRUSTED_POINTS_THRESHOLD = 50;

    protected $fillable = [
        'email', 'display_name', 'auth_provider', 'telegram_id',
        'role', 'points', 'is_trusted', 'is_banned',
    ];

    /** Mirror the DB defaults so a freshly created model reads correctly. */
    protected $attributes = [
        'auth_provider' => 'email',
        'role' => 'user',
        'points' => 0,
        'is_trusted' => false,
        'is_banned' => false,
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'points'     => 'integer',
            'is_trusted' => 'boolean',
            'is_banned'  => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin' && ! $this->is_banned;
    }

    /** Filament reads `name` by default; this table has display_name. */
    public function getFilamentName(): string
    {
        return $this->display_name ?? $this->email ?? 'Admin';
    }

    /** Trusted contributors and admins skip the moderation queue. */
    public function isTrustedContributor(): bool
    {
        return $this->role === 'admin' || $this->is_trusted;
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'submitted_by');
    }

    /** Adds points and auto-promotes to trusted at the threshold. */
    public function awardPoints(int $points): void
    {
        if ($points <= 0) {
            return;
        }
        $this->increment('points', $points);
        if (! $this->is_trusted && $this->points >= self::TRUSTED_POINTS_THRESHOLD) {
            $this->forceFill(['is_trusted' => true])->save();
        }
    }
}
