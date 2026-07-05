<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Block extends Model
{
    public const UPDATED_AT = null;

    /** Points awarded to the submitter when a block is approved. */
    public const POINTS_ON_APPROVAL = 5;

    protected $fillable = [
        'project_id', 'block_number', 'lat', 'lng', 'notes', 'status',
        'submitted_by', 'verified_count', 'report_count', 'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'lat'            => 'float',
            'lng'            => 'float',
            'verified_count' => 'integer',
            'report_count'   => 'integer',
            'is_verified'    => 'boolean',
            'created_at'     => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(BlockEdit::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(BlockVerification::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(BlockReport::class);
    }
}
