<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('zomato_id')->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->json('cuisines')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->string('thumb_url')->nullable();
            $table->string('image_url')->nullable();
            $table->json('hours')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestamps();

            // Composite index for proximity queries
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
