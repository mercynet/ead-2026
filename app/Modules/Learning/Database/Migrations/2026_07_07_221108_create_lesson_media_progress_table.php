<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_media_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_media_id')->constrained('lesson_media')->cascadeOnDelete();
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->json('watch_sessions')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_watched_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'lesson_media_id'], 'lesson_media_progress_unique');
            $table->index(['lesson_media_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_media_progress');
    }
};
