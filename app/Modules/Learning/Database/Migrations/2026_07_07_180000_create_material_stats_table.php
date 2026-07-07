<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('course_material_id')->unique()->constrained('course_materials')->cascadeOnDelete();
            $table->unsignedInteger('total_downloads')->default(0);
            $table->unsignedInteger('downloads_today')->default(0);
            $table->unsignedInteger('downloads_week')->default(0);
            $table->unsignedInteger('downloads_month')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stats');
    }
};
