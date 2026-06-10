<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_customizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('draft_settings')->nullable();
            $table->json('published_settings')->nullable();
            $table->timestamp('last_published_at')->nullable();
            $table->boolean('has_pending_changes')->default(false);
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index(['tenant_id', 'has_pending_changes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_customizations');
    }
};
