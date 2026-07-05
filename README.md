# Addis Condo Finder — Server (API + Admin Panel)

One Laravel 13 app that is the entire backend:

- **`/api/*`** — the REST API the Flutter app talks to (projects,
  blocks, search, auth, votes, corrections, leaderboard). The free
  Telegram bot sign-in flow lives here too.
- **`/admin`** — the Filament 5 moderation panel: approval queues with
  embedded OpenStreetMap pin previews, side-by-side review of suggested
  corrections, a reports queue, and user management (trust, ban,
  promote, points).
- **`/app`** — optionally serves the Flutter web build (static files in
  `public/app/`).

## Local development — zero setup

```sh
php artisan serve        # → http://localhost:8000
```

That's all. On the first request the app creates a SQLite database
(`database/database.sqlite`), migrates it, and seeds the 10 real Addis
condominium areas plus a local-only admin: **admin@local / admin123**.

- API: `http://localhost:8000/api/health`
- Panel: `http://localhost:8000/admin`
- Tests: `./vendor/bin/pest` (28 feature tests over the whole API)

## Deploying to shared hosting (cPanel)

1. Upload this whole folder (including `vendor/`) into `public_html/`.
2. Rename **`.env.production`** to **`.env`** (replacing the local one)
   and fill in the 6 marked values: domain, MySQL credentials from
   cPanel, and your admin email + password. Optionally add
   `TELEGRAM_BOT_TOKEN` for free Telegram sign-in (see below).
3. Open the site. Done — the schema installs and seeds itself on the
   first page load, including your admin account.

The bundled `.htaccess` routes every request through `public/`, so no
subdomain or document-root changes are needed. Requires PHP 8.3+
(cPanel → MultiPHP Manager). Re-uploading the folder is a safe deploy —
all state lives in MySQL.

To also host the web app: `flutter build web --release
--base-href=/app/ --dart-define-from-file=env.json` (the base href
matters — without it the page loads blank), then upload `build/web/`
as `public_html/public/app/` and set `CONDO_WEB_APP_URL` in `.env`.

## Sign in with Telegram (optional, free)

Message [@BotFather](https://t.me/BotFather) on Telegram, send
`/newbot`, pick a name and username, and paste the token it gives you
into `.env` as `TELEGRAM_BOT_TOKEN`. That's the whole setup — the
server discovers the bot's username and registers its webhook by
itself, and the app shows the "Continue with Telegram" button
automatically. Leave the value empty to hide the button.

Users sign in by tapping the button (opens the bot chat) and pressing
**Start** — no password, no OTP, no per-message fees. On plain-HTTP
local dev, where Telegram can't call a webhook, the server falls back
to fetching bot updates while the app polls, so the flow also works at
`http://localhost:8000`.

## Notes

- Never run `php artisan migrate:fresh` against the production MySQL —
  it wipes the community's data. Plain `migrate` is safe.
- Moderation model: submissions start `pending` and appear on the map
  when approved (Moderation → Projects/Blocks). Approvals award points
  (project 10, block 5, correction 3, vote 1); at 50 points users
  become trusted and skip the queue. 3+ reports auto-pull a block's
  Verified badge into the Reports queue.
