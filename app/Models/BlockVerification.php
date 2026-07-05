<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One "this location is correct" vote per user per block. */
final class BlockVerification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['block_id', 'user_id'];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
