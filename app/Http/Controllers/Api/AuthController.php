<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\ApiShape;
use App\Http\Controllers\Controller;
use App\Models\AuthToken;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /** POST /api/auth/register */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $email = mb_strtolower($data['email']);

        if (User::query()->where('email', $email)->exists()) {
            return response()->json([
                'error' => 'email_exists',
                'message' => 'An account with this email already exists.',
            ], 409);
        }

        $user = User::query()->create([
            'email' => $email,
            'display_name' => $data['display_name'],
            'auth_provider' => 'email',
        ]);
        $user->forceFill(['password_hash' => Hash::make($data['password'])])->save();

        return response()->json([
            'token' => AuthToken::issue($user),
            'user' => ApiShape::user($user),
        ], 201);
    }

    /** POST /api/auth/login */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', mb_strtolower($data['email']))->first();
        if ($user === null
            || $user->password_hash === null
            || ! Hash::check($data['password'], $user->password_hash)) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => 'Incorrect email or password.',
            ], 401);
        }
        if ($user->is_banned) {
            return response()->json([
                'error' => 'banned',
                'message' => 'This account has been suspended.',
            ], 403);
        }

        return response()->json([
            'token' => AuthToken::issue($user),
            'user' => ApiShape::user($user),
        ]);
    }

    /** POST /api/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        AuthToken::query()
            ->where('token_hash', hash('sha256', (string) $request->bearerToken()))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** GET /api/auth/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json(ApiShape::user($request->user()));
    }

    /** POST /api/auth/forgot — always 200 so accounts can't be probed. */
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = mb_strtolower($data['email']);

        // Per-email cap on top of the route's per-IP throttle: without it
        // an attacker could spam someone's inbox from many addresses.
        if (RateLimiter::tooManyAttempts("forgot:$email", 3)) {
            return response()->json(['ok' => true]);
        }
        RateLimiter::hit("forgot:$email", 3600);

        $user = User::query()
            ->where('email', $email)
            ->whereNotNull('password_hash')
            ->first();
        if ($user !== null) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            PasswordReset::query()->create([
                'user_id' => $user->id,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes(15),
            ]);
            Mail::raw(
                "Your password reset code is: $code\n\n"
                ."It expires in 15 minutes. If you didn't request this, ignore this email.",
                fn ($mail) => $mail->to($email)
                    ->subject(config('app.name').' password reset code'),
            );
        }

        return response()->json(['ok' => true]);
    }

    /** POST /api/auth/reset — codes die after 5 wrong attempts. */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::query()->where('email', mb_strtolower($data['email']))->first();
        $reset = $user?->id === null ? null : PasswordReset::query()
            ->where('user_id', $user->id)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        $invalid = response()->json([
            'error' => 'invalid_code',
            'message' => 'The reset code is invalid or has expired.',
        ], 400);

        if ($reset === null || $reset->attempts >= PasswordReset::MAX_ATTEMPTS) {
            return $invalid;
        }
        if (! hash_equals($reset->code_hash, hash('sha256', $data['code']))) {
            $reset->attempts++;
            $reset->used = $reset->attempts >= PasswordReset::MAX_ATTEMPTS;
            $reset->save();

            return $invalid;
        }

        $user->forceFill(['password_hash' => Hash::make($data['password'])])->save();
        $reset->forceFill(['used' => true])->save();
        // Sign out all existing sessions after a password change.
        AuthToken::query()->where('user_id', $user->id)->delete();

        return response()->json(['ok' => true]);
    }
}
