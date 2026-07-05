<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-suggested correction ("block 15 is here, not there").
 * Approving applies new_lat/new_lng/new_notes to the block and awards
 * points to the suggester.
 */
final class BlockEdit extends Model
{
    public const UPDATED_AT = null;

    /** Points awarded on approval — keep in sync with the PHP API. */
    public const POINTS_ON_APPROVAL = 3;

    protected $fillable = [
        'block_id', 'user_id', 'new_lat', 'new_lng', 'new_notes', 'reason',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'new_lat'     => 'float',
            'new_lng'     => 'float',
            'reviewed_at' => 'immutable_datetime',
            'created_at'  => 'immutable_datetime',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function suggester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
