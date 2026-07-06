<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\CondominiumController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TelegramAuthController;
use Illuminate\Support\Facades\Route;

/*
 * REST API for the Flutter app. Paths and JSON shapes intentionally
 * mirror what the app's ApiClient expects (API_BASE_URL = .../api).
 */

// ------------------------------------------------------------- public
Route::get('/condominiums', [CondominiumController::class, 'index']);
Route::get('/condominiums/{id}/projects', [CondominiumController::class, 'projects'])
    ->whereNumber('id');
Route::get('/projects', [ProjectController::class, 'index']);
Route::get('/projects/{id}/blocks', [ProjectController::class, 'blocks'])
    ->whereNumber('id');
Route::get('/blocks', [ProjectController::class, 'allBlocks']);
Route::get('/blocks/{id}', [BlockController::class, 'show'])->whereNumber('id');
Route::get('/search', SearchController::class);
Route::get('/leaderboard', LeaderboardController::class)
    ->middleware('api.token:optional');

Route::view('/privacy', 'privacy');
Route::get('/health', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ok',
    'auth' => [
        'email' => true,
        'telegram' => config('condo.telegram_bot_token') !== '',
    ],
]));
Route::redirect('/', '/api/health');

// --------------------------------------------------------- email auth
Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,60');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,15');
Route::post('/auth/forgot', [AuthController::class, 'forgot'])
    ->middleware('throttle:5,60');
Route::post('/auth/reset', [AuthController::class, 'reset'])
    ->middleware('throttle:10,15');

// ------------------------------------------------------ telegram auth
Route::post('/auth/telegram/start', [TelegramAuthController::class, 'start'])
    ->middleware('throttle:10,15');
Route::post('/auth/telegram/poll', [TelegramAuthController::class, 'poll'])
    ->middleware('throttle:60,1');
Route::post('/auth/telegram/webhook', [TelegramAuthController::class, 'webhook']);

// ------------------------------------------------------ authenticated
Route::middleware('api.token')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/me', [ProfileController::class, 'update']);
    Route::delete('/me', [ProfileController::class, 'destroy']);
    Route::post('/me/photo', [ProfileController::class, 'uploadPhoto'])
        ->middleware('throttle:10,15');
    Route::delete('/me/photo', [ProfileController::class, 'deletePhoto']);
    Route::get('/me/contributions', [ProfileController::class, 'contributions']);

    Route::post('/condominiums', [CondominiumController::class, 'store']);
    Route::post('/condominiums/{id}/suggest-area', fn (\Illuminate\Http\Request $r, int $id) => app(CondominiumController::class)->suggestArea($r, 'condominium', $id))
        ->whereNumber('id');
    Route::post('/projects/{id}/suggest-area', fn (\Illuminate\Http\Request $r, int $id) => app(CondominiumController::class)->suggestArea($r, 'project', $id))
        ->whereNumber('id');
    Route::post('/maps-link', [CondominiumController::class, 'resolveMapsLink'])
        ->middleware('throttle:30,60');
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::post('/blocks', [BlockController::class, 'store']);
    Route::post('/blocks/{id}/verify', [BlockController::class, 'verify'])
        ->whereNumber('id');
    Route::post('/blocks/{id}/report', [BlockController::class, 'report'])
        ->whereNumber('id');
    Route::post('/blocks/{id}/suggest-edit', [BlockController::class, 'suggestEdit'])
        ->whereNumber('id');
});
