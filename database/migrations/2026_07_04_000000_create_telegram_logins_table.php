<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Short-lived hand-off rows for the Telegram bot sign-in flow. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_logins', function (Blueprint $table): void {
            $table->id();
            $table->string('nonce', 64)->unique();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->cascadeOnDelete();
            $table->string('token', 128)->nullable();
            $table->string('error', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_logins');
    }
};
