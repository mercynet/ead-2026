<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('rateable');
            $table->unsignedTinyInteger('stars');
            $table->string('reaction')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'rateable_type', 'rateable_id'], 'ratings_unique_per_user_rateable');
            $table->index(['tenant_id', 'rateable_type', 'rateable_id'], 'ratings_rateable_index');
            $table->index(['tenant_id', 'user_id'], 'ratings_tenant_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
