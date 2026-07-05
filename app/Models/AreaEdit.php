<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-redrawn footprint for a condominium or project, waiting for
 * admin review. Approving replaces the target's polygon (and centroid).
 */
final class AreaEdit extends Model
{
    public const UPDATED_AT = null;

    /** Points awarded when an area correction is approved. */
    public const POINTS_ON_APPROVAL = 3;

    protected $fillable = [
        'target_type', 'target_id', 'user_id', 'new_polygon', 'reason',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'new_polygon' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function suggester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The condominium or project this edit redraws. */
    public function target(): Condominium|Project|null
    {
        return $this->target_type === 'condominium'
            ? Condominium::query()->find($this->target_id)
            : Project::query()->find($this->target_id);
    }

    /** Applies the redrawn polygon (and recomputed centroid). */
    public function apply(): void
    {
        $target = $this->target();
        if ($target === null) {
            return;
        }
        [$lat, $lng] = Condominium::centroidOf($this->new_polygon);
        $target->update([
            'polygon' => $this->new_polygon,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }
}
