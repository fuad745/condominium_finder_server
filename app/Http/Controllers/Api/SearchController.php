<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Condominium;
use App\Models\Project;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * GET /api/search?q= — approved condominiums + projects + blocks,
     * grouped client-side.
     *
     * Matching is word-based: every whitespace-separated token of the
     * query must appear somewhere in "name + area name" (or
     * "project name + block number" for blocks), in any order — so
     * "feche koye", "koye 435" and "435 koye" all match. Rows whose
     * name starts with the query rank first.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $escape = fn (string $s): string => str_replace(['%', '_'], ['\\%', '\\_'], $s);
        $tokens = preg_split('/\s+/u', $q) ?: [];
        $prefixLike = $escape($q).'%';

        // Cross-driver "a || ' ' || b" — MySQL needs CONCAT().
        $concat = DB::connection()->getDriverName() === 'sqlite'
            ? fn (string $a, string $b): string => "($a || ' ' || COALESCE($b, ''))"
            : fn (string $a, string $b): string => "CONCAT($a, ' ', COALESCE($b, ''))";

        $allTokensMatch = function (string $haystack) use ($tokens, $escape) {
            return function (Builder $w) use ($haystack, $tokens, $escape): void {
                foreach ($tokens as $token) {
                    $w->whereRaw("$haystack like ?", ['%'.$escape($token).'%']);
                }
            };
        };

        $condos = Condominium::query()
            ->where('status', 'approved')
            ->where($allTokensMatch($concat('name', 'area_name')))
            ->orderByRaw('CASE WHEN name like ? THEN 0 ELSE 1 END, name', [$prefixLike])
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
            ->where($allTokensMatch($concat('name', 'area_name')))
            ->orderByRaw('CASE WHEN name like ? THEN 0 ELSE 1 END, name', [$prefixLike])
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

        // "koye 435" hits block 435 in Koye Feche: tokens match against
        // the combined "<project name> <block number>".
        $blocks = Block::query()
            ->select('blocks.*')
            ->with('project')
            ->join('projects', 'projects.id', '=', 'blocks.project_id')
            ->where('blocks.status', 'approved')
            ->where('projects.status', 'approved')
            ->where($allTokensMatch($concat('projects.name', 'blocks.block_number')))
            ->orderByRaw(
                'CASE WHEN blocks.block_number like ? THEN 0 ELSE 1 END, blocks.block_number',
                [$prefixLike],
            )
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
