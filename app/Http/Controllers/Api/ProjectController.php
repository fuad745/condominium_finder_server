<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\ApiShape;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Condominium;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /** GET /api/projects — approved projects with approved-block counts. */
    public function index(): JsonResponse
    {
        $projects = Project::query()
            ->with('condominium')
            ->where('status', 'approved')
            ->withCount(['blocks as known_blocks' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get();

        return response()->json($projects->map(ApiShape::project(...)));
    }

    /** GET /api/projects/{id}/blocks — approved blocks, numerically sorted. */
    public function blocks(int $id): JsonResponse
    {
        $numeric = DB::connection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
        $blocks = Block::query()
            ->with(['project', 'submitter'])
            ->where('project_id', $id)
            ->where('status', 'approved')
            ->orderByRaw("CAST(block_number AS $numeric), block_number")
            ->get();

        return response()->json($blocks->map(ApiShape::block(...)));
    }

    /**
     * GET /api/blocks — every approved block, so the map can pin them
     * without a project being selected first (and cache them for
     * offline use). An optional `bbox=minLat,minLng,maxLat,maxLng`
     * keeps the payload viewport-sized once the dataset outgrows
     * ship-everything.
     */
    public function allBlocks(Request $request): JsonResponse
    {
        $query = Block::query()
            ->with(['project', 'submitter'])
            ->where('status', 'approved')
            ->whereHas('project', fn ($q) => $q->where('status', 'approved'));

        $bbox = array_map(floatval(...), array_filter(
            explode(',', (string) $request->query('bbox', '')),
            is_numeric(...),
        ));
        if (count($bbox) === 4) {
            [$minLat, $minLng, $maxLat, $maxLng] = $bbox;
            $query->whereBetween('lat', [min($minLat, $maxLat), max($minLat, $maxLat)])
                ->whereBetween('lng', [min($minLng, $maxLng), max($minLng, $maxLng)]);
        }

        return response()->json($query->get()->map(ApiShape::block(...)));
    }

    /** POST /api/projects — pending unless the author is trusted. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'area_name' => ['nullable', 'string', 'max:100'],
            'condominium_id' => ['required', 'integer', 'exists:condominiums,id'],
            ...CondominiumController::POLYGON_RULES,
            'block_range_start' => ['nullable', 'integer'],
            'block_range_end' => ['nullable', 'integer', 'gte:block_range_start'],
        ]);

        $user = $request->user();
        $status = $user->isTrustedContributor() ? 'approved' : 'pending';
        $start = $data['block_range_start'] ?? null;
        $end = $data['block_range_end'] ?? null;
        [$lat, $lng] = Condominium::centroidOf($data['polygon']);

        $project = Project::query()->create([
            ...$data,
            'lat' => $lat,
            'lng' => $lng,
            'total_blocks' => ($start !== null && $end !== null) ? $end - $start + 1 : null,
            'status' => $status,
        ]);
        $project->creator()->associate($user)->save();

        if ($status === 'approved') {
            $user->awardPoints(Project::POINTS_ON_APPROVAL);
        }

        return response()->json(['id' => (string) $project->id, 'status' => $status], 201);
    }
}
