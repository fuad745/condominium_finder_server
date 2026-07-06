<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer Telegram sign-in profiles: the bot now captures the user's
 * @username and profile photo automatically, and their phone number
 * when they tap the "share contact" button in the bot chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_username', 64)->nullable()->after('telegram_id');
            $table->string('phone', 32)->nullable()->after('telegram_username');
            $table->string('photo_url')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['telegram_username', 'phone', 'photo_url']);
        });
    }
};
