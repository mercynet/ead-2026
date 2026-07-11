<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->morphs('rateable');
            $table->decimal('average_stars', 4, 2)->default(0);
            $table->unsignedInteger('total_ratings')->default(0);
            $table->unsignedInteger('five_stars')->default(0);
            $table->unsignedInteger('four_stars')->default(0);
            $table->unsignedInteger('three_stars')->default(0);
            $table->unsignedInteger('two_stars')->default(0);
            $table->unsignedInteger('one_star')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('dislikes_count')->default(0);
            $table->timestamp('last_rated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'rateable_type', 'rateable_id'], 'rating_stats_unique_rateable');
            $table->index(['tenant_id', 'rateable_type', 'rateable_id'], 'rating_stats_rateable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_stats');
    }
};
