<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('normalized_name');
            $table->boolean('is_system')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'is_system']);
            $table->index(['parent_id']);
            $table->unique(['tenant_id', 'parent_id', 'slug', 'deleted_at'], 'categories_tenant_parent_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
