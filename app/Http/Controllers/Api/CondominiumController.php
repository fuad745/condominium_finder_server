<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\ApiShape;
use App\Http\Controllers\Controller;
use App\Models\AreaEdit;
use App\Models\Condominium;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CondominiumController extends Controller
{
    /** Shared validation for a drawn footprint: ≥3 [lat,lng] corners. */
    public const POLYGON_RULES = [
        'polygon' => ['required', 'array', 'min:3', 'max:60'],
        'polygon.*' => ['required', 'array', 'size:2'],
        'polygon.*.0' => ['required', 'numeric', 'between:-90,90'],
        'polygon.*.1' => ['required', 'numeric', 'between:-180,180'],
    ];

    /** GET /api/condominiums — approved, with project counts. */
    public function index(): JsonResponse
    {
        $condos = Condominium::query()
            ->where('status', 'approved')
            ->withCount(['projects as projects_count' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get();

        return response()->json($condos->map(ApiShape::condominium(...)));
    }

    /** GET /api/condominiums/{id}/projects — approved projects inside. */
    public function projects(int $id): JsonResponse
    {
        $projects = Project::query()
            ->with('condominium')
            ->where('condominium_id', $id)
            ->where('status', 'approved')
            ->withCount(['blocks as known_blocks' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get();

        return response()->json($projects->map(ApiShape::project(...)));
    }

    /** POST /api/condominiums — drawn footprint; pending unless trusted. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'area_name' => ['nullable', 'string', 'max:100'],
            ...self::POLYGON_RULES,
        ]);

        $user = $request->user();
        $status = $user->isTrustedContributor() ? 'approved' : 'pending';
        [$lat, $lng] = Condominium::centroidOf($data['polygon']);

        $condo = Condominium::query()->create([
            ...$data,
            'lat' => $lat,
            'lng' => $lng,
            'created_by' => $user->id,
            'status' => $status,
        ]);

        if ($status === 'approved') {
            $user->awardPoints(Condominium::POINTS_ON_APPROVAL);
        }

        return response()->json(['id' => (string) $condo->id, 'status' => $status], 201);
    }

    /**
     * POST /api/condominiums/{id}/suggest-area and
     * POST /api/projects/{id}/suggest-area — a redrawn footprint.
     * Applied after admin review, or immediately for trusted users.
     */
    public function suggestArea(Request $request, string $type, int $id): JsonResponse
    {
        $data = $request->validate([
            ...self::POLYGON_RULES,
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $target = $type === 'condominium'
            ? Condominium::query()->where('status', 'approved')->findOrFail($id)
            : Project::query()->where('status', 'approved')->findOrFail($id);

        $user = $request->user();
        $trusted = $user->isTrustedContributor();

        $edit = AreaEdit::query()->create([
            'target_type' => $type,
            'target_id' => $target->id,
            'user_id' => $user->id,
            'new_polygon' => $data['polygon'],
            'reason' => $data['reason'] ?? null,
            'status' => $trusted ? 'approved' : 'pending',
            'reviewed_by' => $trusted ? $user->id : null,
            'reviewed_at' => $trusted ? now() : null,
        ]);

        if ($trusted) {
            $edit->apply();
            $user->awardPoints(AreaEdit::POINTS_ON_APPROVAL);
        }

        return response()->json(['status' => $trusted ? 'approved' : 'pending'], 201);
    }

    /**
     * POST /api/maps-link — extracts coordinates from a shared Google
     * Maps link (blocks only; areas must be drawn). Follows the redirect
     * of maps.app.goo.gl short links server-side.
     */
    public function resolveMapsLink(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2000']]);
        $url = trim($data['url']);

        $invalid = response()->json([
            'error' => 'invalid_maps_link',
            'message' => 'That does not look like a Google Maps link with a pin.',
        ], 422);

        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $allowed = ['google.com', 'maps.google.com', 'www.google.com',
            'maps.app.goo.gl', 'goo.gl', 'www.google.com.et'];
        if (! in_array(strtolower($host), $allowed, true)) {
            return $invalid;
        }

        // Short links carry no coordinates — follow to the full URL.
        if (str_contains($host, 'goo.gl')) {
            try {
                $response = Http::timeout(10)->get($url);
                $url = (string) $response->effectiveUri();
            } catch (\Throwable) {
                return $invalid;
            }
        }

        $coords = self::coordsFromMapsUrl($url);
        if ($coords === null) {
            return $invalid;
        }

        return response()->json(['lat' => $coords[0], 'lng' => $coords[1]]);
    }

    /** @return array{0: float, 1: float}|null */
    public static function coordsFromMapsUrl(string $url): ?array
    {
        // Place pin: ...!3d9.0054!4d38.7636 (most precise, prefer it).
        if (preg_match('/!3d(-?\d+\.?\d*)!4d(-?\d+\.?\d*)/', $url, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }
        // Query pin: ?q=9.0054,38.7636 (also q=loc:...).
        if (preg_match('/[?&]q=(?:loc:)?(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/', $url, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }
        // Camera position: /@9.0054,38.7636,17z — fallback.
        if (preg_match('/@(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }

        return null;
    }
}
