<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\ApiShape;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /** PATCH /api/me — display name and/or phone. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);
        $user = $request->user();
        $user->forceFill(array_intersect_key(
            $data,
            array_flip(['display_name', 'phone']),
        ))->save();

        return response()->json(ApiShape::user($user));
    }

    /**
     * POST /api/me/photo — profile photo upload (multipart "photo").
     * Telegram sign-ins get a photo automatically when their privacy
     * settings allow it; this lets everyone else (or privacy-restricted
     * Telegram users) set one by hand.
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $user = $request->user();
        $file = $request->file('photo');

        $ext = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        @mkdir(public_path('uploads/avatars'), 0775, true);
        $this->deletePhotoFile($user);
        // Timestamped name so a replaced photo busts browser caches.
        $name = 'u_'.$user->id.'_'.now()->format('YmdHis').'.'.$ext;
        $file->move(public_path('uploads/avatars'), $name);
        $user->forceFill(['photo_url' => 'uploads/avatars/'.$name])->save();

        return response()->json(ApiShape::user($user));
    }

    /** DELETE /api/me/photo — remove the profile photo. */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->deletePhotoFile($user);
        $user->forceFill(['photo_url' => null])->save();

        return response()->json(ApiShape::user($user));
    }

    /** Removes the current photo file; only touches our avatars dir. */
    private function deletePhotoFile(User $user): void
    {
        $current = $user->photo_url;
        if (is_string($current)
            && str_starts_with($current, 'uploads/avatars/')
            && is_file(public_path($current))) {
            @unlink(public_path($current));
        }
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
