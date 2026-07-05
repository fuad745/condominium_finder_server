<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    /** GET /api/leaderboard — top contributors + the caller's own rank. */
    public function __invoke(Request $request): JsonResponse
    {
        $top = User::query()
            ->where('is_banned', false)
            ->where('points', '>', 0)
            ->orderByDesc('points')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->values()
            ->map(fn (User $u, int $i) => [
                'rank' => $i + 1,
                'display_name' => $u->display_name ?? 'Contributor',
                'points' => (int) $u->points,
                'is_trusted' => (bool) $u->is_trusted,
            ]);

        $me = null;
        if (($user = $request->user()) !== null) {
            $rank = User::query()
                ->where('is_banned', false)
                ->where(fn ($w) => $w->where('points', '>', $user->points)
                    ->orWhere(fn ($t) => $t->where('points', $user->points)
                        ->where('id', '<', $user->id)))
                ->count() + 1;
            $me = [
                'rank' => $rank,
                'total' => User::query()->where('is_banned', false)->count(),
                'points' => (int) $user->points,
                'is_trusted' => (bool) $user->is_trusted,
            ];
        }

        return response()->json(['top' => $top, 'me' => $me]);
    }
}
