<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('zomato_id')->unique();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('user_name');
            $table->string('user_thumb_url')->nullable();
            $table->decimal('rating', 3, 1);
            $table->text('review_text');
            $table->timestamp('posted_at')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestamps();

            $table->index('restaurant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
