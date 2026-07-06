<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\ApiShape;
use App\Http\Controllers\Controller;
use App\Models\AuthToken;
use App\Models\TelegramLogin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sign in with Telegram — free bot deep-link flow (no OTP, no fees).
 *
 *   1. POST /auth/telegram/start hands the app a one-time code and a
 *      https://t.me/<bot>?start=<code> link, which opens the bot chat.
 *   2. The user taps Start; Telegram delivers "/start <code>" to this
 *      server (webhook on HTTPS; getUpdates polling on plain-HTTP local
 *      dev, where Telegram can't reach us), which issues a bearer token.
 *   3. The app polls POST /auth/telegram/poll until the token is ready.
 *
 * The only configuration is TELEGRAM_BOT_TOKEN from @BotFather: the bot
 * username comes from getMe and the webhook (de)registers itself.
 */
class TelegramAuthController extends Controller
{
    /** A login code is single-use and dies after this many minutes. */
    private const CODE_TTL_MINUTES = 10;

    /** POST /api/auth/telegram/start */
    public function start(): JsonResponse
    {
        if ($this->botToken() === '') {
            return response()->json([
                'error' => 'telegram_disabled',
                'message' => 'Telegram login is not configured on this server.',
            ], 501);
        }

        $username = $this->botUsername();
        if ($username === null) {
            return response()->json([
                'error' => 'telegram_unreachable',
                'message' => 'Could not reach Telegram. Please try again.',
            ], 502);
        }
        $this->syncDelivery();

        TelegramLogin::query()
            ->where('created_at', '<', now()->subMinutes(self::CODE_TTL_MINUTES))
            ->delete();
        $login = TelegramLogin::query()->create(['nonce' => Str::random(32)]);

        return response()->json([
            'code' => $login->nonce,
            'bot_username' => $username,
            'deep_link' => "https://t.me/{$username}?start={$login->nonce}",
        ]);
    }

    /** POST /api/auth/telegram/poll {code} */
    public function poll(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        if (! $this->usesWebhook()) {
            $this->drainUpdates();
        }

        $login = TelegramLogin::query()->where('nonce', $data['code'])->first();
        if ($login === null
            || $login->created_at->addMinutes(self::CODE_TTL_MINUTES)->isPast()) {
            return response()->json([
                'error' => 'telegram_expired',
                'message' => 'The sign-in request expired. Please try again.',
            ], 410);
        }

        if ($login->error !== null) {
            $error = $login->error;
            $login->delete();

            return response()->json([
                'error' => $error,
                'message' => $error === 'banned'
                    ? 'This account has been suspended.'
                    : 'Sign-in failed. Please try again.',
            ], 403);
        }

        $user = $login->token === null ? null : User::query()->find($login->user_id);
        if ($user === null) {
            return response()->json(['status' => 'pending']);
        }

        $token = $login->token;
        $login->delete();

        return response()->json(['token' => $token, 'user' => ApiShape::user($user)]);
    }

    /** POST /api/auth/telegram/webhook — called by Telegram itself. */
    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if (! hash_equals($this->webhookSecret(), $secret)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Invalid webhook secret.',
            ], 403);
        }

        $update = $request->json()->all();
        if (is_array($update)) {
            $this->processUpdate($update);
        }

        return response()->json(['ok' => true]);
    }

    // -------------------------------------------------------- internals

    /**
     * Handles one Telegram update: a "/start <code>" bot message, or a
     * shared contact (the user tapping the "share phone" button).
     */
    private function processUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return;
        }
        $text = (string) ($message['text'] ?? '');
        $from = $message['from'] ?? null;
        $chatId = $message['chat']['id'] ?? null;
        if (! is_array($from) || $chatId === null) {
            return;
        }

        if (is_array($message['contact'] ?? null)) {
            $this->saveSharedContact($message['contact'], $from, $chatId);

            return;
        }

        if (! str_starts_with($text, '/start')) {
            return;
        }

        $nonce = trim(substr($text, strlen('/start')));
        $login = $nonce === '' ? null : TelegramLogin::query()
            ->where('nonce', $nonce)
            ->whereNull('token')
            ->whereNull('error')
            ->where('created_at', '>', now()->subMinutes(self::CODE_TTL_MINUTES))
            ->first();
        if ($login === null) {
            $this->reply($chatId, 'This sign-in link has expired. Open the app and tap "Continue with Telegram" again.');

            return;
        }

        $user = User::query()->where('telegram_id', (int) $from['id'])->first();
        $user ??= User::query()->create([
            'display_name' => trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')) ?: null,
            'auth_provider' => 'telegram',
            'telegram_id' => (int) $from['id'],
        ]);

        if ($user->is_banned) {
            $login->forceFill(['error' => 'banned'])->save();
            $this->reply($chatId, 'This account has been suspended.');

            return;
        }

        $this->syncTelegramProfile($user, $from);

        $login->forceFill([
            'user_id' => $user->id,
            'token' => AuthToken::issue($user),
        ])->save();
        $this->reply($chatId, 'Signed in as '.($user->display_name ?? 'a new contributor').'. You can return to the app now.');

        if ($user->phone === null) {
            $this->api('sendMessage', [
                'chat_id' => $chatId,
                'text' => 'Optional: share your phone number to add it to your profile.',
                'reply_markup' => [
                    'keyboard' => [[[
                        'text' => '📱 Share my phone number',
                        'request_contact' => true,
                    ]]],
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true,
                ],
            ]);
        }
    }

    /** Keeps @username and the profile photo fresh on every sign-in. */
    private function syncTelegramProfile(User $user, array $from): void
    {
        $username = $from['username'] ?? null;
        if (is_string($username) && $username !== $user->telegram_username) {
            $user->forceFill(['telegram_username' => $username])->save();
        }

        try {
            $photos = $this->api('getUserProfilePhotos', [
                'user_id' => (int) $from['id'],
                'limit' => 1,
            ]);
            $sizes = $photos['photos'][0] ?? null;
            if (! is_array($sizes) || $sizes === []) {
                return;
            }
            // Sizes are ordered small → large; ~320px is plenty for the panel.
            $fileId = $sizes[min(1, count($sizes) - 1)]['file_id'] ?? null;
            $file = $fileId === null ? null : $this->api('getFile', ['file_id' => $fileId]);
            $filePath = is_array($file) ? ($file['file_path'] ?? null) : null;
            if (! is_string($filePath)) {
                return;
            }
            // The download URL embeds the bot token, so fetch the bytes
            // once and serve the copy from our own public directory.
            $bytes = Http::timeout(15)
                ->get('https://api.telegram.org/file/bot'.$this->botToken().'/'.$filePath)
                ->body();
            if ($bytes === '') {
                return;
            }
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) ?: 'jpg';
            $relative = 'uploads/avatars/tg_'.$user->telegram_id.'.'.$ext;
            @mkdir(public_path('uploads/avatars'), 0775, true);
            file_put_contents(public_path($relative), $bytes);
            if ($user->photo_url !== $relative) {
                $user->forceFill(['photo_url' => $relative])->save();
            }
        } catch (Throwable) {
            // The photo is a nice-to-have; never block sign-in on it.
        }
    }

    /** The user tapped "share my phone number" in the bot chat. */
    private function saveSharedContact(array $contact, array $from, int|string $chatId): void
    {
        // Only accept the sender's own contact card, not forwarded ones.
        if ((int) ($contact['user_id'] ?? 0) !== (int) $from['id']) {
            return;
        }
        $user = User::query()->where('telegram_id', (int) $from['id'])->first();
        $phone = trim((string) ($contact['phone_number'] ?? ''));
        if ($user === null || $phone === '') {
            return;
        }

        $user->forceFill(['phone' => '+'.ltrim($phone, '+')])->save();
        $this->api('sendMessage', [
            'chat_id' => $chatId,
            'text' => 'Phone number saved — thanks!',
            'reply_markup' => ['remove_keyboard' => true],
        ]);
    }

    /**
     * Local dev (plain HTTP): Telegram can't call our webhook, so each
     * poll drains pending updates via getUpdates instead. A cache lock
     * keeps concurrent pollers from fighting over the update stream.
     */
    private function drainUpdates(): void
    {
        $lock = Cache::lock('telegram:drain', 5);
        if (! $lock->get()) {
            return;
        }
        try {
            $offsetKey = 'telegram:offset:'.md5($this->botToken());
            $params = ['timeout' => 0, 'allowed_updates' => ['message']];
            if (($offset = Cache::get($offsetKey)) !== null) {
                $params['offset'] = $offset;
            }
            $updates = $this->api('getUpdates', $params);
            foreach (is_array($updates) ? $updates : [] as $update) {
                if (is_array($update) && isset($update['update_id'])) {
                    Cache::forever($offsetKey, $update['update_id'] + 1);
                    $this->processUpdate($update);
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Registers the webhook once per APP_URL when it's HTTPS (Telegram
     * requires TLS); otherwise deregisters it so getUpdates works.
     */
    private function syncDelivery(): void
    {
        $mode = $this->usesWebhook() ? 'webhook:'.$this->webhookUrl() : 'poll';
        $key = 'telegram:delivery:'.md5($this->botToken());
        if (Cache::get($key) === $mode) {
            return;
        }

        $ok = $this->usesWebhook()
            ? $this->api('setWebhook', [
                'url' => $this->webhookUrl(),
                'secret_token' => $this->webhookSecret(),
                'allowed_updates' => ['message'],
            ])
            : $this->api('deleteWebhook');
        if ($ok !== null) {
            Cache::forever($key, $mode);
        }
    }

    private function webhookUrl(): string
    {
        return url('/api/auth/telegram/webhook');
    }

    private function usesWebhook(): bool
    {
        return str_starts_with($this->webhookUrl(), 'https://');
    }

    /** Derived from the app key — no extra .env value to configure. */
    private function webhookSecret(): string
    {
        return hash_hmac('sha256', 'telegram-webhook', (string) config('app.key'));
    }

    private function botToken(): string
    {
        return (string) config('condo.telegram_bot_token');
    }

    /** The bot's @username via getMe, cached forever per token. */
    private function botUsername(): ?string
    {
        $key = 'telegram:username:'.md5($this->botToken());
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        $me = $this->api('getMe');
        $username = is_array($me) ? ($me['username'] ?? null) : null;
        if (is_string($username)) {
            Cache::forever($key, $username);

            return $username;
        }

        return null;
    }

    private function reply(int|string $chatId, string $text): void
    {
        $this->api('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
    }

    /** Bot API call; returns the `result` payload or null on failure. */
    private function api(string $method, array $params = []): mixed
    {
        try {
            $response = Http::timeout(15)
                ->post('https://api.telegram.org/bot'.$this->botToken().'/'.$method, $params);

            return $response->json('ok') === true
                ? ($response->json('result') ?? true)
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
