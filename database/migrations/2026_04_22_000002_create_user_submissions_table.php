<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('chat_id');
            $table->string('type');
            $table->string('file_id');
            $table->unsignedBigInteger('message_id');
            $table->jsonb('raw_update');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('chat_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_submissions');
    }
};
