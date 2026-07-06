<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->autoSetupDatabase();
    }

    /**
     * Zero-step install: on the very first web request (local SQLite or
     * freshly-configured server MySQL) the schema is migrated and the
     * seed data loaded automatically. Once the users table exists this
     * is a single cheap check per request.
     */
    private function autoSetupDatabase(): void
    {
        if ($this->app->runningInConsole()) {
            return; // artisan migrate/seed run normally
        }

        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $file = config('database.connections.sqlite.database');
                if (is_string($file) && $file !== ':memory:' && ! is_file($file)) {
                    @mkdir(dirname($file), 0775, true);
                    touch($file);
                }
            }
            // The newest schema change doubles as the "schema is current"
            // probe, so upgrades also apply pending migrations automatically.
            $hasUsers = Schema::hasTable('users');
            if ($hasUsers && Schema::hasColumn('users', 'telegram_username')) {
                return;
            }
            Artisan::call('migrate', ['--force' => true]);
            if (! $hasUsers) {
                Artisan::call('db:seed', ['--force' => true]);
            }
        } catch (Throwable) {
            // Unreachable/misconfigured DB: let the real request surface
            // the connection error instead of dying during boot.
        }
    }
}
