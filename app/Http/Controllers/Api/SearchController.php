<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** GET /api/search?q= — approved projects + blocks, grouped client-side. */
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        $condos = \App\Models\Condominium::query()
            ->where('status', 'approved')
            ->where(fn ($w) => $w->where('name', 'like', $like)
                ->orWhere('area_name', 'like', $like))
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'result_type' => 'condominium',
                'id' => (string) $c->id,
                'title' => $c->name,
                'subtitle' => $c->area_name ?? '',
                'lat' => (float) $c->lat,
                'lng' => (float) $c->lng,
            ]);

        $projects = Project::query()
            ->where('status', 'approved')
            ->where(fn ($w) => $w->where('name', 'like', $like)
                ->orWhere('area_name', 'like', $like))
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (Project $p) => [
                'result_type' => 'project',
                'id' => (string) $p->id,
                'title' => $p->name,
                'subtitle' => $p->area_name ?? '',
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
            ]);

        // "koye 435" should hit block 435 in Koye Feche too, so match the
        // block number alone or the "<project name> <number>" combination.
        $fullName = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
            ? "(projects.name || ' ' || blocks.block_number)"
            : "CONCAT(projects.name, ' ', blocks.block_number)";
        $blocks = Block::query()
            ->select('blocks.*')
            ->with('project')
            ->join('projects', 'projects.id', '=', 'blocks.project_id')
            ->where('blocks.status', 'approved')
            ->where('projects.status', 'approved')
            ->where(fn ($w) => $w->where('blocks.block_number', 'like', $like)
                ->orWhereRaw("$fullName like ?", [$like]))
            ->orderBy('blocks.block_number')
            ->limit(15)
            ->get()
            ->map(fn (Block $b) => [
                'result_type' => 'block',
                'id' => (string) $b->id,
                'title' => 'Block '.$b->block_number,
                'subtitle' => $b->project?->name ?? '',
                'lat' => (float) $b->lat,
                'lng' => (float) $b->lng,
            ]);

        return response()->json($condos->concat($projects)->concat($blocks)->values());
    }
}
