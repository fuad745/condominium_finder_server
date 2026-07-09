<?php

declare(strict_types=1);

use App\Models\AuthToken;
use App\Models\Block;
use App\Models\Condominium;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

const TEST_POLYGON = [[8.90, 38.82], [8.91, 38.82], [8.91, 38.83], [8.90, 38.83]];

function makeCondo(array $attrs = []): Condominium
{
    return Condominium::query()->create(array_merge([
        'name' => 'Koye Feche Condominium',
        'area_name' => 'Koye Feche',
        'lat' => 8.905,
        'lng' => 38.825,
        'polygon' => TEST_POLYGON,
        'status' => 'approved',
    ], $attrs));
}

function makeProject(array $attrs = []): Project
{
    $attrs['condominium_id'] ??= makeCondo()->id;

    return Project::query()->create(array_merge([
        'name' => 'Koye Feche Project 16',
        'area_name' => 'Koye Feche',
        'lat' => 8.9033,
        'lng' => 38.8233,
        'polygon' => TEST_POLYGON,
        'radius_meters' => 450,
        'status' => 'approved',
    ], $attrs));
}

function makeBlock(Project $project, array $attrs = []): Block
{
    return Block::query()->create(array_merge([
        'project_id' => $project->id,
        'block_number' => '435',
        'lat' => 8.9032,
        'lng' => 38.8233,
        'status' => 'approved',
    ], $attrs));
}

function makeUser(array $attrs = []): array
{
    $user = User::query()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'display_name' => 'Tester',
        'auth_provider' => 'email',
    ], $attrs));
    $user->forceFill(['password_hash' => Hash::make('secret1')])->save();

    return [$user, AuthToken::issue($user)];
}

// ------------------------------------------------------------- reads

it('lists only approved projects with approved block counts', function (): void {
    $approved = makeProject();
    makeBlock($approved);
    makeBlock($approved, ['block_number' => '9', 'status' => 'pending']);
    makeProject(['name' => 'Hidden', 'status' => 'pending']);

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', (string) $approved->id)
        ->assertJsonPath('0.known_blocks', 1);
});

it('hides pending blocks from the public endpoints', function (): void {
    $project = makeProject();
    makeBlock($project);
    $pending = makeBlock($project, ['block_number' => '501', 'status' => 'pending']);

    $this->getJson("/api/projects/{$project->id}/blocks")
        ->assertOk()->assertJsonCount(1);
    $this->getJson("/api/blocks/{$pending->id}")
        ->assertNotFound()->assertJsonPath('error', 'not_found');
});

it('changes the data version when content changes', function (): void {
    $project = makeProject();

    $before = $this->getJson('/api/data-version')->assertOk()->json('version');
    expect($before)->toBeString();

    // Unchanged data → identical fingerprint.
    expect($this->getJson('/api/data-version')->json('version'))->toBe($before);

    makeBlock($project);
    expect($this->getJson('/api/data-version')->json('version'))
        ->not->toBe($before);
});

it('searches approved projects and blocks', function (): void {
    makeBlock(makeProject());

    $results = $this->getJson('/api/search?q=koye')->assertOk()->json();
    expect(collect($results)->pluck('result_type'))->toContain('project', 'block');
});

it('filters /api/blocks by bbox when given', function (): void {
    $project = makeProject();
    makeBlock($project); // 8.9032, 38.8233
    makeBlock($project, ['block_number' => '900', 'lat' => 9.05, 'lng' => 38.70]);

    $this->getJson('/api/blocks?bbox=8.90,38.82,8.91,38.83')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.block_number', '435');

    // Malformed bbox falls back to everything rather than erroring.
    $this->getJson('/api/blocks?bbox=oops')->assertOk()->assertJsonCount(2);
});

it('matches search words in any order across combined fields', function (): void {
    makeBlock(makeProject()); // "Koye Feche Project 16", block 435

    // "<project> <block>" and the reverse both hit the block.
    foreach (['koye 435', '435 koye'] as $q) {
        $results = $this->getJson('/api/search?q='.urlencode($q))->assertOk()->json();
        expect(collect($results)->pluck('result_type'))->toContain('block');
    }

    // Project name words out of order still match.
    $results = $this->getJson('/api/search?q='.urlencode('feche koye'))->assertOk()->json();
    expect(collect($results)->pluck('result_type'))->toContain('project');

    // Condominium results are included too.
    $results = $this->getJson('/api/search?q=koye')->assertOk()->json();
    expect(collect($results)->pluck('result_type'))->toContain('condominium');
});

it('lists every approved block with contributor credit', function (): void {
    $project = makeProject();
    [$user] = makeUser(['display_name' => 'Sara', 'points' => 12, 'is_trusted' => true]);
    makeBlock($project, ['submitted_by' => $user->id]);
    makeBlock($project, ['block_number' => '9', 'status' => 'pending']);

    $this->getJson('/api/blocks')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.submitter_name', 'Sara')
        ->assertJsonPath('0.submitter_points', 12)
        ->assertJsonPath('0.submitter_trusted', true);
});


// -------------------------------------------------------------- auth

it('registers, logs in and rejects wrong passwords', function (): void {
    $this->postJson('/api/auth/register', [
        'email' => 'a@b.com', 'password' => 'secret1', 'display_name' => 'Abel',
    ])->assertCreated()->assertJsonPath('user.role', 'user')
        ->assertJsonStructure(['token']);

    $this->postJson('/api/auth/login', ['email' => 'a@b.com', 'password' => 'secret1'])
        ->assertOk();
    $this->postJson('/api/auth/login', ['email' => 'a@b.com', 'password' => 'nope99'])
        ->assertUnauthorized()->assertJsonPath('error', 'invalid_credentials');
});

it('requires auth for writes', function (): void {
    $this->postJson('/api/blocks', [])->assertUnauthorized()
        ->assertJsonPath('error', 'unauthorized');
});

it('blocks banned users', function (): void {
    [, $token] = makeUser(['is_banned' => true]);

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertForbidden()->assertJsonPath('error', 'banned');
});

// -------------------------------------------------------- moderation

it('queues submissions from normal users and publishes trusted ones', function (): void {
    $project = makeProject();
    [, $token] = makeUser();

    $this->withToken($token)->postJson('/api/blocks', [
        'project_id' => $project->id, 'block_number' => '12',
        'lat' => 8.9, 'lng' => 38.8,
    ])->assertCreated()->assertJsonPath('status', 'pending');

    [$trusted, $trustedToken] = makeUser(['is_trusted' => true]);
    $this->withToken($trustedToken)->postJson('/api/blocks', [
        'project_id' => $project->id, 'block_number' => '13',
        'lat' => 8.9, 'lng' => 38.8,
    ])->assertCreated()->assertJsonPath('status', 'approved');

    expect($trusted->refresh()->points)->toBe(Block::POINTS_ON_APPROVAL);
});

// ---------------------------------------------------------- hierarchy

it('lists condominiums with their approved projects', function (): void {
    $condo = makeCondo();
    makeProject(['condominium_id' => $condo->id]);
    makeProject([
        'condominium_id' => $condo->id,
        'name' => 'Hidden', 'status' => 'pending',
    ]);

    $this->getJson('/api/condominiums')
        ->assertOk()->assertJsonCount(1)
        ->assertJsonPath('0.known_projects', 1)
        ->assertJsonPath('0.polygon.0.0', 8.9);

    $this->getJson("/api/condominiums/{$condo->id}/projects")
        ->assertOk()->assertJsonCount(1)
        ->assertJsonPath('0.condominium_name', 'Koye Feche Condominium');
});

it('creates condominiums and projects from drawn polygons', function (): void {
    [, $token] = makeUser(['is_trusted' => true]);

    $condoId = $this->withToken($token)->postJson('/api/condominiums', [
        'name' => 'Drawn Condominium',
        'polygon' => TEST_POLYGON,
    ])->assertCreated()->assertJsonPath('status', 'approved')->json('id');

    $this->withToken($token)->postJson('/api/projects', [
        'name' => 'Drawn Project 1',
        'condominium_id' => (int) $condoId,
        'polygon' => TEST_POLYGON,
    ])->assertCreated()->assertJsonPath('status', 'approved');

    // Centroid derived from the ring.
    $listed = collect($this->getJson('/api/projects')->json())
        ->firstWhere('name', 'Drawn Project 1');
    expect($listed['lat'])->toEqualWithDelta(8.905, 0.001)
        ->and($listed['polygon'])->toHaveCount(4);
});

it('queues redrawn areas and applies trusted ones immediately', function (): void {
    $condo = makeCondo();
    $newRing = [[8.90, 38.82], [8.92, 38.82], [8.92, 38.84], [8.90, 38.84]];

    [, $token] = makeUser();
    $this->withToken($token)->postJson("/api/condominiums/{$condo->id}/suggest-area", [
        'polygon' => $newRing, 'reason' => 'grew to the east',
    ])->assertCreated()->assertJsonPath('status', 'pending');
    expect($condo->refresh()->polygon)->toBe(TEST_POLYGON);

    [, $trustedToken] = makeUser(['is_trusted' => true]);
    $this->withToken($trustedToken)->postJson("/api/condominiums/{$condo->id}/suggest-area", [
        'polygon' => $newRing,
    ])->assertCreated()->assertJsonPath('status', 'approved');
    expect($condo->refresh()->polygon)->toBe($newRing);
});

it('extracts coordinates from shared Google Maps links', function (): void {
    [, $token] = makeUser();

    $this->withToken($token)->postJson('/api/maps-link', [
        'url' => 'https://www.google.com/maps/place/Block+435/@8.9,38.8,17z/data=!3m1!4b1!8m2!3d9.0054321!4d38.7636987',
    ])->assertOk()
        ->assertJsonPath('lat', 9.0054321)
        ->assertJsonPath('lng', 38.7636987);

    $this->withToken($token)->postJson('/api/maps-link', [
        'url' => 'https://maps.google.com/?q=8.9032,38.8233',
    ])->assertOk()->assertJsonPath('lat', 8.9032);

    $this->withToken($token)->postJson('/api/maps-link', [
        'url' => 'https://evil.example.com/@9.0,38.7',
    ])->assertStatus(422)->assertJsonPath('error', 'invalid_maps_link');
});

it('rejects duplicate block numbers within a project', function (): void {
    $project = makeProject();
    makeBlock($project, ['block_number' => '56']);
    [, $token] = makeUser();

    $this->withToken($token)->postJson('/api/blocks', [
        'project_id' => $project->id, 'block_number' => '56',
        'lat' => 8.9, 'lng' => 38.8,
    ])->assertStatus(409)->assertJsonPath('error', 'duplicate_block');
});

it('refuses self-verification and counts independent votes', function (): void {
    $project = makeProject();
    [$submitter, $submitterToken] = makeUser();
    $block = makeBlock($project, ['submitted_by' => $submitter->id]);

    $this->withToken($submitterToken)->postJson("/api/blocks/{$block->id}/verify")
        ->assertStatus(422)->assertJsonPath('error', 'own_block');

    foreach (range(1, 3) as $i) {
        [, $voterToken] = makeUser();
        $response = $this->withToken($voterToken)
            ->postJson("/api/blocks/{$block->id}/verify")->assertOk();
    }
    $response->assertJsonPath('verified_count', 3)->assertJsonPath('is_verified', true);
});

it('pulls the verified badge after 3 reports', function (): void {
    $block = makeBlock(makeProject(), ['is_verified' => true, 'verified_count' => 3]);

    foreach (range(1, 3) as $i) {
        [, $token] = makeUser();
        $response = $this->withToken($token)
            ->postJson("/api/blocks/{$block->id}/report", ['reason' => 'wrong'])
            ->assertOk();
    }
    $response->assertJsonPath('report_count', 3)->assertJsonPath('is_verified', false);
});

it('applies trusted corrections immediately, queues others', function (): void {
    $block = makeBlock(makeProject());
    [, $token] = makeUser();

    $this->withToken($token)->postJson("/api/blocks/{$block->id}/suggest-edit", [
        'lat' => 9.0, 'lng' => 38.9,
    ])->assertCreated()->assertJsonPath('status', 'pending');
    expect((float) $block->refresh()->lat)->toBe(8.9032);

    [, $trustedToken] = makeUser(['is_trusted' => true]);
    $this->withToken($trustedToken)->postJson("/api/blocks/{$block->id}/suggest-edit", [
        'lat' => 9.0, 'lng' => 38.9,
    ])->assertCreated()->assertJsonPath('status', 'approved');
    expect((float) $block->refresh()->lat)->toBe(9.0);
});

// ----------------------------------------------------------- profile

it('shows pending contributions to their owner', function (): void {
    $project = makeProject();
    [$user, $token] = makeUser();
    makeBlock($project, [
        'block_number' => '77', 'status' => 'pending', 'submitted_by' => $user->id,
    ]);

    $this->withToken($token)->getJson('/api/me/contributions')
        ->assertOk()
        ->assertJsonPath('blocks.0.block_number', '77')
        ->assertJsonPath('blocks.0.status', 'pending');
});

it('deletes accounts but keeps contributions anonymised', function (): void {
    $project = makeProject();
    [$user, $token] = makeUser();
    $block = makeBlock($project, ['submitted_by' => $user->id, 'block_number' => '88']);

    $this->withToken($token)->deleteJson('/api/me')->assertOk();

    expect(User::query()->find($user->id))->toBeNull()
        ->and($block->refresh()->submitted_by)->toBeNull();
});

it('ranks contributors on the leaderboard', function (): void {
    [, $token] = makeUser(['points' => 5, 'display_name' => 'Sara']);
    makeUser(['points' => 12, 'display_name' => 'Abel']);

    $this->withToken($token)->getJson('/api/leaderboard')
        ->assertOk()
        ->assertJsonPath('top.0.display_name', 'Abel')
        ->assertJsonPath('me.rank', 2);
});

// ------------------------------------------------------ reset codes

it('invalidates reset codes after 5 wrong attempts', function (): void {
    [$user] = makeUser();
    App\Models\PasswordReset::query()->create([
        'user_id' => $user->id,
        'code_hash' => hash('sha256', '123456'),
        'expires_at' => now()->addMinutes(15),
    ]);

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/auth/reset', [
            'email' => $user->email, 'code' => '000000', 'password' => 'newpass1',
        ])->assertStatus(400);
    }
    // Correct code no longer works — the code burned out.
    $this->postJson('/api/auth/reset', [
        'email' => $user->email, 'code' => '123456', 'password' => 'newpass1',
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_code');
});
