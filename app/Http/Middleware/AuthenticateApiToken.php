<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuthToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the app's API (auth_tokens table).
 *
 * `api.token` — requires a valid token; 401 otherwise, 403 when banned.
 * `api.token:optional` — resolves the user when a token is present but
 * lets guests through (used by /leaderboard).
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $user = AuthToken::resolve($request->bearerToken());

        if ($user === null && $mode !== 'optional') {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Sign in required.',
            ], 401);
        }

        if ($user !== null && $user->is_banned) {
            if ($mode === 'optional') {
                $user = null;
            } else {
                return response()->json([
                    'error' => 'banned',
                    'message' => 'This account has been suspended.',
                ], 403);
            }
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
