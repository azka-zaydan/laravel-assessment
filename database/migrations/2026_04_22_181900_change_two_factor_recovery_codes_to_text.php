<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `encrypted:array` cast writes an opaque base64 Laravel-encrypted blob,
     * which is not valid JSON — Postgres rejects it on a `json` column (22P02).
     * SQLite silently accepts the blob (json is text-affinity there), so regression
     * coverage must exercise pgsql. Store it as `text` like Laravel Fortify does.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN two_factor_recovery_codes TYPE text USING two_factor_recovery_codes::text');

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException("Unsupported driver [{$driver}] for this migration. Add a branch before running.");
        }

        $affected = (int) DB::table('users')->whereNotNull('two_factor_recovery_codes')->count();
        if ($affected > 0) {
            Log::warning('migration.two_factor_recovery_codes.sqlite_data_loss', ['affected' => $affected]);
        }

        Schema::table('users', function ($table): void {
            $table->dropColumn('two_factor_recovery_codes');
        });
        Schema::table('users', function ($table): void {
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });
    }

    /**
     * The encrypted blob is not valid JSON, so a direct `ALTER TYPE ... USING ::json`
     * would re-raise 22P02. NULL the column first — callers must re-enrol 2FA after
     * rollback.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::table('users')->update(['two_factor_recovery_codes' => null]);
            DB::statement('ALTER TABLE users ALTER COLUMN two_factor_recovery_codes TYPE json USING two_factor_recovery_codes::json');

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException("Unsupported driver [{$driver}] for this migration. Add a branch before running.");
        }

        Schema::table('users', function ($table): void {
            $table->dropColumn('two_factor_recovery_codes');
        });
        Schema::table('users', function ($table): void {
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });
    }
};
