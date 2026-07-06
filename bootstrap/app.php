<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The host fronts the app with a reverse proxy (openresty). Without
        // this, Laravel sees the proxy's IP for every visitor, so per-IP
        // rate limits on the auth routes become one shared global bucket.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'api.token' => App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // The Flutter app expects every API error as {error, message}.
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'validation',
                    'message' => collect($e->errors())->flatten()->first(),
                ], 422);
            }
        });
        $exceptions->renderable(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'rate_limited',
                    'message' => 'Too many attempts. Please wait and try again.',
                ], 429);
            }
        });
        $exceptions->renderable(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'not_found',
                    'message' => 'Not found.',
                ], 404);
            }
        });
        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'forbidden',
                    'message' => $e->getMessage() ?: 'Not allowed.',
                ], 403);
            }
        });
        $exceptions->renderable(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => $e->getStatusCode() === 401 ? 'unauthorized' : 'error',
                    'message' => $e->getMessage() ?: 'Request failed.',
                ], $e->getStatusCode());
            }
        });
    })->create();
