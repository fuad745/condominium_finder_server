<?php

declare(strict_types=1);

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function actingUserToken(): array
{
    $user = User::query()->create([
        'email' => 'photo@test.local',
        'display_name' => 'Photo Tester',
    ]);

    return [$user, AuthToken::issue($user)];
}

it('uploads and removes a profile photo', function () {
    [$user, $token] = actingUserToken();

    $upload = $this->withToken($token)->post('/api/me/photo', [
        'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
    ]);

    $upload->assertOk();
    $photoUrl = $upload->json('photo_url');
    expect($photoUrl)->toContain('uploads/avatars/u_'.$user->id);

    $relative = 'uploads/avatars/'.basename((string) $photoUrl);
    expect(is_file(public_path($relative)))->toBeTrue();

    $remove = $this->withToken($token)->deleteJson('/api/me/photo');
    $remove->assertOk();
    expect($remove->json('photo_url'))->toBeNull();
    expect(is_file(public_path($relative)))->toBeFalse();
});

it('rejects non-image uploads', function () {
    [, $token] = actingUserToken();

    $this->withToken($token)->post('/api/me/photo', [
        'photo' => UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('updates phone via PATCH /me', function () {
    [, $token] = actingUserToken();

    $response = $this->withToken($token)->patchJson('/api/me', [
        'phone' => '+251911223344',
    ]);

    $response->assertOk();
    expect($response->json('phone'))->toBe('+251911223344');
    // display_name untouched when omitted
    expect($response->json('display_name'))->toBe('Photo Tester');
});
