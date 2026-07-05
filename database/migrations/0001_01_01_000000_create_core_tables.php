<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The whole Addis Condo Finder schema. Runs identically on SQLite
 * (local dev) and MySQL/MariaDB (shared hosting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable()->unique();
            $table->string('password_hash')->nullable();
            $table->string('display_name', 100)->nullable();
            $table->string('auth_provider', 20)->default('email');
            $table->unsignedBigInteger('telegram_id')->nullable()->unique();
            $table->string('role', 20)->default('user');
            $table->integer('points')->default(0)->index();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->rememberToken();
            $table->timestamp('created_at')->useCurrent();
        });

        // Bearer tokens for the app (stored SHA-256 hashed).
        Schema::create('auth_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });

        // 6-digit password reset codes (hashed, 15-min expiry, 5 attempts).
        Schema::create('password_resets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64);
            $table->dateTime('expires_at');
            $table->boolean('used')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        // Top of the hierarchy: a condominium (e.g. "Koye Feche") holds
        // many projects, each of which holds many blocks. Its footprint
        // is a user-drawn polygon: JSON [[lat,lng], ...].
        Schema::create('condominiums', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('area_name', 100)->nullable()->index();
            $table->decimal('lat', 10, 7);   // polygon centroid
            $table->decimal('lng', 10, 7);
            $table->text('polygon');
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condominium_id')->nullable()
                ->constrained('condominiums')->cascadeOnDelete();
            $table->string('name', 150)->index();
            $table->string('area_name', 100)->nullable()->index();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            // User-drawn area polygon (JSON [[lat,lng],...]). The legacy
            // radius/width/height stay as fallbacks for old payloads.
            $table->text('polygon')->nullable();
            $table->integer('radius_meters')->default(300);
            $table->integer('width_meters')->default(600);
            $table->integer('height_meters')->default(600);
            $table->integer('block_range_start')->nullable();
            $table->integer('block_range_end')->nullable();
            $table->integer('total_blocks')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('block_number', 20)->index();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('notes', 500)->nullable();
            $table->foreignId('submitted_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->integer('verified_count')->default(0);
            $table->integer('report_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['project_id', 'block_number']);
        });

        // One confirmation vote per user per block.
        Schema::create('block_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['block_id', 'user_id']);
        });

        // One "this looks wrong" report per user per block.
        Schema::create('block_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['block_id', 'user_id']);
        });

        // Suggested area corrections for condominiums/projects — a
        // redrawn polygon awaiting admin review.
        Schema::create('area_edits', function (Blueprint $table): void {
            $table->id();
            $table->string('target_type', 20); // 'condominium' | 'project'
            $table->unsignedBigInteger('target_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('new_polygon');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });

        // Suggested corrections ("block 15 is here, not there").
        Schema::create('block_edits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('new_lat', 10, 7);
            $table->decimal('new_lng', 10, 7);
            $table->string('new_notes', 500)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_edits');
        Schema::dropIfExists('block_edits');
        Schema::dropIfExists('block_reports');
        Schema::dropIfExists('block_verifications');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('condominiums');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('auth_tokens');
        Schema::dropIfExists('users');
    }
};
