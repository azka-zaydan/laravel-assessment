<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `encrypted:array` cast writes an opaque base64 Laravel-encrypted blob,
     * which is not valid JSON — Postgres rejects it on a `json` column (22P02).
     * Store it as `text` like Laravel Fortify does.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN two_factor_recovery_codes TYPE text USING two_factor_recovery_codes::text');

            return;
        }

        // SQLite / MySQL fallback — drop & re-add as text.
        Schema::table('users', function ($table): void {
            $table->dropColumn('two_factor_recovery_codes');
        });
        Schema::table('users', function ($table): void {
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN two_factor_recovery_codes TYPE json USING two_factor_recovery_codes::json');

            return;
        }

        Schema::table('users', function ($table): void {
            $table->dropColumn('two_factor_recovery_codes');
        });
        Schema::table('users', function ($table): void {
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });
    }
};
