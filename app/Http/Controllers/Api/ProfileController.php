<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\ApiShape;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /** PATCH /api/me — display name. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $user = $request->user();
        $user->forceFill(['display_name' => $data['display_name']])->save();

        return response()->json(ApiShape::user($user));
    }

    /**
     * DELETE /api/me — permanent account deletion (Play Store requires
     * it). Contributions survive anonymously: created_by/submitted_by
     * go NULL, tokens/votes/reports cascade away.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->delete();

        return response()->json(['ok' => true]);
    }

    /** GET /api/me/contributions — my projects + blocks incl. status. */
    public function contributions(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = $user->projects()->orderByDesc('created_at')->get()
            ->map(fn ($p) => [
                'id' => (string) $p->id,
                'name' => $p->name,
                'area_name' => $p->area_name,
                'status' => $p->status,
                'created_at' => $p->created_at?->toDateTimeString(),
            ]);

        $blocks = $user->blocks()->with('project')->orderByDesc('created_at')->get()
            ->map(fn ($b) => [
                'id' => (string) $b->id,
                'project_id' => (string) $b->project_id,
                'block_number' => $b->block_number,
                'project_name' => $b->project?->name,
                'lat' => (float) $b->lat,
                'lng' => (float) $b->lng,
                'notes' => $b->notes,
                'verified_count' => (int) $b->verified_count,
                'is_verified' => (bool) $b->is_verified,
                'status' => $b->status,
                'created_at' => $b->created_at?->toDateTimeString(),
            ]);

        return response()->json(['projects' => $projects, 'blocks' => $blocks]);
    }
}
