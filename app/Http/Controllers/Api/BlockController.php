<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\ApiShape;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\BlockEdit;
use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    /** GET /api/blocks/{id} — one approved block. */
    public function show(int $id): JsonResponse
    {
        $block = Block::query()->with(['project', 'submitter'])
            ->where('status', 'approved')
            ->findOrFail($id);

        return response()->json(ApiShape::block($block));
    }

    /** POST /api/blocks — pending unless the author is trusted. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'block_number' => ['required', 'string', 'max:20'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! Project::query()->whereKey($data['project_id'])->exists()) {
            return response()->json([
                'error' => 'not_found', 'message' => 'Project not found.',
            ], 404);
        }

        $user = $request->user();
        $status = $user->isTrustedContributor() ? 'approved' : 'pending';
        $number = mb_strtoupper(trim($data['block_number']));

        try {
            $block = Block::query()->create([
                ...$data,
                'block_number' => $number,
                'status' => $status,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'error' => 'duplicate_block',
                'message' => "Block $number already exists in this project.",
            ], 409);
        }
        $block->submitter()->associate($user)->save();

        if ($status === 'approved') {
            $user->awardPoints(Block::POINTS_ON_APPROVAL);
        }

        return response()->json(['id' => (string) $block->id, 'status' => $status], 201);
    }

    /** POST /api/blocks/{id}/verify — once per user, never your own. */
    public function verify(Request $request, int $id): JsonResponse
    {
        $block = Block::query()->where('status', 'approved')->findOrFail($id);
        $user = $request->user();

        // Self-verification would let a submitter fast-track their own
        // pin toward "Verified" — the whole point is independent eyes.
        if ($block->submitted_by === $user->id) {
            return response()->json([
                'error' => 'own_block',
                'message' => 'You cannot confirm a block you added yourself.',
            ], 422);
        }

        // Insert-first (unique on block_id+user_id): a double-tap or two
        // concurrent requests can both pass an exists() pre-check, so the
        // constraint is the only reliable "once per user" arbiter.
        $recorded = false;
        try {
            $block->verifications()->create(['user_id' => $user->id]);
            $recorded = true;
            $user->awardPoints(1);
        } catch (UniqueConstraintViolationException) {
            // Already confirmed — idempotent no-op.
        }

        $count = $block->verifications()->count();
        $block->update([
            'verified_count' => $count,
            'is_verified' => $count >= 3,
        ]);

        return response()->json([
            'recorded' => $recorded,
            'verified_count' => $count,
            'is_verified' => $count >= 3,
        ]);
    }

    /** POST /api/blocks/{id}/report — 3+ reports pull the Verified badge. */
    public function report(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $block = Block::query()->where('status', 'approved')->findOrFail($id);
        $user = $request->user();

        // Insert-first for the same reason as verify(): the unique
        // constraint, not a racy exists() pre-check, enforces one report
        // per user.
        $recorded = false;
        try {
            $block->reports()->create([
                'user_id' => $user->id,
                'reason' => $data['reason'] ?? null,
            ]);
            $recorded = true;
        } catch (UniqueConstraintViolationException) {
            // Already reported — idempotent no-op.
        }

        $count = $block->reports()->count();
        $block->update([
            'report_count' => $count,
            'is_verified' => $count >= 3 ? false : $block->is_verified,
        ]);

        return response()->json([
            'recorded' => $recorded,
            'report_count' => $count,
            'is_verified' => (bool) $block->refresh()->is_verified,
        ]);
    }

    /**
     * POST /api/blocks/{id}/suggest-edit — "block 15 is here, not there".
     * Applied after admin review, or immediately for trusted users.
     */
    public function suggestEdit(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $block = Block::query()->where('status', 'approved')->findOrFail($id);
        $user = $request->user();
        $trusted = $user->isTrustedContributor();

        BlockEdit::query()->create([
            'block_id' => $block->id,
            'user_id' => $user->id,
            'new_lat' => $data['lat'],
            'new_lng' => $data['lng'],
            'new_notes' => $data['notes'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => $trusted ? 'approved' : 'pending',
            'reviewed_by' => $trusted ? $user->id : null,
            'reviewed_at' => $trusted ? now() : null,
        ]);

        if ($trusted) {
            $block->update([
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'notes' => $data['notes'] ?? $block->notes,
            ]);
            $user->awardPoints(BlockEdit::POINTS_ON_APPROVAL);
        }

        return response()->json(['status' => $trusted ? 'approved' : 'pending'], 201);
    }
}
