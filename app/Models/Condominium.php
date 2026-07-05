<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Top of the hierarchy: a condominium (e.g. "Koye Feche") contains many
 * projects; projects contain blocks. Its footprint is a user-drawn
 * polygon stored as JSON [[lat,lng], ...].
 */
final class Condominium extends Model
{
    public const UPDATED_AT = null;

    /** Eloquent would pluralize this to the Latin "condominia". */
    protected $table = 'condominiums';

    /** Points awarded to the creator when a condominium is approved. */
    public const POINTS_ON_APPROVAL = 15;

    protected $fillable = [
        'name', 'area_name', 'lat', 'lng', 'polygon', 'created_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'polygon' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Centroid of a [[lat,lng],...] ring — used for search and fly-to. */
    public static function centroidOf(array $polygon): array
    {
        $lat = array_sum(array_column($polygon, 0)) / count($polygon);
        $lng = array_sum(array_column($polygon, 1)) / count($polygon);

        return [round($lat, 7), round($lng, 7)];
    }
}
