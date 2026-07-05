<?php

/*
 * Addis Condo Finder application settings. Everything has a sensible
 * default; override in .env only when needed.
 */
return [

    // Bearer-token lifetime for the mobile/web app.
    'token_ttl_days' => (int) env('CONDO_TOKEN_TTL_DAYS', 90),

    // Sign in with Telegram (optional; empty hides the button in the
    // app). Bot token from @BotFather — the bot username and webhook
    // are discovered/registered automatically.
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

];
