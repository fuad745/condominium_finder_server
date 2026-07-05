<?php

declare(strict_types=1);

use App\Models\TelegramLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** The webhook secret the controller derives from the app key. */
function telegramWebhookSecret(): string
{
    return hash_hmac('sha256', 'telegram-webhook', (string) config('app.key'));
}

/** Configures the bot and fakes the Telegram Bot API. */
function fakeTelegram(array $updates = []): void
{
    config(['condo.telegram_bot_token' => '12345:TEST-TOKEN']);

    Http::fake(function ($request) use ($updates) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/getMe') => Http::response([
                'ok' => true,
                'result' => ['id' => 12345, 'is_bot' => true, 'username' => 'AddisCondoBot'],
            ]),
            str_contains($url, '/getUpdates') => Http::response([
                'ok' => true, 'result' => $updates,
            ]),
            default => Http::response(['ok' => true, 'result' => true]),
        };
    });
}

/** A Telegram "/start <nonce>" update as the Bot API delivers it. */
function startUpdate(string $nonce, int $telegramId = 777000111): array
{
    return [
        'update_id' => 42,
        'message' => [
            'message_id' => 1,
            'from' => [
                'id' => $telegramId,
                'is_bot' => false,
                'first_name' => 'Abebe',
                'last_name' => 'Kebede',
            ],
            'chat' => ['id' => $telegramId, 'type' => 'private'],
            'text' => "/start $nonce",
        ],
    ];
}

it('reports telegram login as disabled when no bot token is set', function (): void {
    config(['condo.telegram_bot_token' => '']);

    $this->getJson('/api/health')->assertOk()
        ->assertJsonPath('auth.telegram', false);
    $this->postJson('/api/auth/telegram/start')
        ->assertStatus(501)
        ->assertJsonPath('error', 'telegram_disabled');
});

it('signs in a new user through the telegram bot flow', function (): void {
    fakeTelegram();

    $start = $this->postJson('/api/auth/telegram/start')
        ->assertOk()
        ->assertJsonPath('bot_username', 'AddisCondoBot')
        ->json();
    expect($start['deep_link'])
        ->toBe('https://t.me/AddisCondoBot?start='.$start['code']);

    // Not confirmed yet.
    $this->postJson('/api/auth/telegram/poll', ['code' => $start['code']])
        ->assertOk()
        ->assertJsonPath('status', 'pending');

    // Telegram delivers the /start message to the webhook.
    $this->postJson('/api/auth/telegram/webhook', startUpdate($start['code']), [
        'X-Telegram-Bot-Api-Secret-Token' => telegramWebhookSecret(),
    ])->assertOk();

    $poll = $this->postJson('/api/auth/telegram/poll', ['code' => $start['code']])
        ->assertOk()
        ->assertJsonPath('user.display_name', 'Abebe Kebede')
        ->assertJsonPath('user.auth_provider', 'telegram')
        ->json();

    // The issued token works, and the hand-off row is single-use.
    $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$poll['token']])
        ->assertOk()
        ->assertJsonPath('telegram_id', 777000111);
    expect(TelegramLogin::query()->count())->toBe(0);
    $this->postJson('/api/auth/telegram/poll', ['code' => $start['code']])
        ->assertStatus(410);
});

it('signs an existing telegram user into the same account', function (): void {
    fakeTelegram();
    $existing = User::query()->create([
        'display_name' => 'Abebe K.',
        'auth_provider' => 'telegram',
        'telegram_id' => 777000111,
    ]);

    $code = $this->postJson('/api/auth/telegram/start')->json('code');
    $this->postJson('/api/auth/telegram/webhook', startUpdate($code), [
        'X-Telegram-Bot-Api-Secret-Token' => telegramWebhookSecret(),
    ])->assertOk();

    $this->postJson('/api/auth/telegram/poll', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('user.id', (string) $existing->id);
    expect(User::query()->count())->toBe(1);
});

it('rejects webhook calls without the shared secret', function (): void {
    fakeTelegram();
    $code = $this->postJson('/api/auth/telegram/start')->json('code');

    $this->postJson('/api/auth/telegram/webhook', startUpdate($code))
        ->assertStatus(403);
    $this->postJson('/api/auth/telegram/webhook', startUpdate($code), [
        'X-Telegram-Bot-Api-Secret-Token' => 'wrong',
    ])->assertStatus(403);

    $this->postJson('/api/auth/telegram/poll', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('status', 'pending');
});

it('refuses banned users and expired or unknown codes', function (): void {
    fakeTelegram();
    User::query()->create([
        'auth_provider' => 'telegram',
        'telegram_id' => 777000111,
        'is_banned' => true,
    ]);

    $code = $this->postJson('/api/auth/telegram/start')->json('code');
    $this->postJson('/api/auth/telegram/webhook', startUpdate($code), [
        'X-Telegram-Bot-Api-Secret-Token' => telegramWebhookSecret(),
    ])->assertOk();
    $this->postJson('/api/auth/telegram/poll', ['code' => $code])
        ->assertStatus(403)
        ->assertJsonPath('error', 'banned');

    $this->postJson('/api/auth/telegram/poll', ['code' => 'does-not-exist'])
        ->assertStatus(410)
        ->assertJsonPath('error', 'telegram_expired');

    $stale = TelegramLogin::query()->create(['nonce' => 'stale-nonce-value']);
    $stale->newQuery()->whereKey($stale->id)
        ->update(['created_at' => now()->subMinutes(11)]);
    $this->postJson('/api/auth/telegram/poll', ['code' => 'stale-nonce-value'])
        ->assertStatus(410);
});

it('completes the flow via getUpdates when no https webhook is possible', function (): void {
    $login = null;
    // First start() call creates the row; getUpdates then delivers the
    // confirmation on the next poll (the local-dev delivery path).
    config(['condo.telegram_bot_token' => '12345:TEST-TOKEN']);
    Http::fake(function ($request) use (&$login) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/getMe') => Http::response([
                'ok' => true,
                'result' => ['id' => 12345, 'is_bot' => true, 'username' => 'AddisCondoBot'],
            ]),
            str_contains($url, '/getUpdates') => Http::response([
                'ok' => true,
                'result' => $login === null ? [] : [startUpdate($login)],
            ]),
            default => Http::response(['ok' => true, 'result' => true]),
        };
    });

    $login = $this->postJson('/api/auth/telegram/start')->json('code');

    $this->postJson('/api/auth/telegram/poll', ['code' => $login])
        ->assertOk()
        ->assertJsonStructure(['token', 'user'])
        ->assertJsonPath('user.display_name', 'Abebe Kebede');

    // The webhook was deregistered for polling mode.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/deleteWebhook'));
});
