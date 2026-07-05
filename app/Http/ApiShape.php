<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\Block;
use App\Models\Condominium;
use App\Models\Project;
use App\Models\User;

/**
 * JSON shapes consumed by the Flutter app (lib/models/models.dart).
 * Ids travel as strings and timestamps as "Y-m-d H:i:s" — keep it that
 * way; the app's parsers rely on it.
 */
final class ApiShape
{
    /** @return array<string, mixed> */
    public static function condominium(Condominium $c): array
    {
        return [
            'id' => (string) $c->id,
            'name' => $c->name,
            'area_name' => $c->area_name,
            'lat' => (float) $c->lat,
            'lng' => (float) $c->lng,
            'polygon' => $c->polygon,
            'known_projects' => (int) ($c->projects_count ?? 0),
            'status' => $c->status,
            'created_by' => $c->created_by !== null ? (string) $c->created_by : null,
            'created_at' => $c->created_at?->toDateTimeString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function project(Project $p): array
    {
        return [
            'id' => (string) $p->id,
            'condominium_id' => $p->condominium_id !== null
                ? (string) $p->condominium_id : null,
            'condominium_name' => $p->relationLoaded('condominium')
                ? $p->condominium?->name : null,
            'name' => $p->name,
            'area_name' => $p->area_name,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'polygon' => $p->polygon,
            'radius_meters' => (int) $p->radius_meters,
            'width_meters' => (int) ($p->width_meters ?? $p->radius_meters * 2),
            'height_meters' => (int) ($p->height_meters ?? $p->radius_meters * 2),
            'block_range_start' => $p->block_range_start,
            'block_range_end' => $p->block_range_end,
            'total_blocks' => $p->total_blocks,
            'known_blocks' => (int) ($p->known_blocks ?? $p->blocks_count ?? 0),
            'status' => $p->status,
            'created_by' => $p->created_by !== null ? (string) $p->created_by : null,
            'created_at' => $p->created_at?->toDateTimeString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function block(Block $b): array
    {
        return [
            'id' => (string) $b->id,
            'project_id' => (string) $b->project_id,
            'project_name' => $b->relationLoaded('project') ? $b->project?->name : null,
            'block_number' => $b->block_number,
            'lat' => (float) $b->lat,
            'lng' => (float) $b->lng,
            'notes' => $b->notes,
            'verified_count' => (int) $b->verified_count,
            'report_count' => (int) $b->report_count,
            'is_verified' => (bool) $b->is_verified,
            'status' => $b->status,
            'submitted_by' => $b->submitted_by !== null ? (string) $b->submitted_by : null,
            // Contributor credit shown on the block sheet.
            'submitter_name' => $b->relationLoaded('submitter')
                ? $b->submitter?->display_name : null,
            'submitter_points' => $b->relationLoaded('submitter') && $b->submitter !== null
                ? (int) $b->submitter->points : null,
            'submitter_trusted' => $b->relationLoaded('submitter') && $b->submitter !== null
                ? (bool) $b->submitter->is_trusted : null,
            'created_at' => $b->created_at?->toDateTimeString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function user(User $u): array
    {
        return [
            'id' => (string) $u->id,
            'email' => $u->email,
            'display_name' => $u->display_name,
            'auth_provider' => $u->auth_provider,
            'telegram_id' => $u->telegram_id,
            'role' => $u->role,
            'points' => (int) $u->points,
            'is_trusted' => (bool) $u->is_trusted,
        ];
    }
}
