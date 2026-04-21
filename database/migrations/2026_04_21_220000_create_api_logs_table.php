<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('request_id', 26)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 8);
            $table->string('path', 500);
            $table->string('route_name', 255)->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->jsonb('headers')->default('{}');
            $table->jsonb('body')->default('{}');
            $table->smallInteger('response_status');
            $table->integer('response_size_bytes');
            $table->integer('duration_ms');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('created_at');
            $table->index('method');
            $table->index('response_status');
            $table->index('route_name');
        });

        // Postgres expression index for path prefix lookups
        DB::statement('CREATE INDEX api_logs_path_prefix_index ON api_logs (substring(path, 1, 100))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS api_logs_path_prefix_index');
        Schema::dropIfExists('api_logs');
    }
};
