<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Project extends Model
{
    public const UPDATED_AT = null;

    /** Points awarded to the creator when a project is approved. */
    public const POINTS_ON_APPROVAL = 10;

    protected $fillable = [
        'name', 'area_name', 'lat', 'lng', 'radius_meters', 'created_by',
        'condominium_id', 'polygon', 'width_meters', 'height_meters',
        'block_range_start', 'block_range_end', 'total_blocks', 'status',
    ];

    protected function casts(): array
    {
        return [
            'lat'           => 'float',
            'lng'           => 'float',
            'polygon'       => 'array',
            'radius_meters' => 'integer',
            'width_meters'  => 'integer',
            'height_meters' => 'integer',
            'created_at'    => 'immutable_datetime',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }
}
