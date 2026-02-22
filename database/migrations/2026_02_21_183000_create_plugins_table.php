<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->string('version', 32);
            $table->text('description')->nullable();
            $table->string('author')->default('Platform Team');
            $table->string('license')->default('Proprietary');
            $table->unsignedInteger('price_cents')->default(0);
            $table->enum('billing_cycle', ['monthly', 'yearly', 'one_time'])->default('monthly');
            $table->string('category')->default('general');
            $table->string('directory_name')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_core')->default(false);
            $table->json('config')->nullable();
            $table->json('dependencies')->nullable();
            $table->json('requirements')->nullable();
            $table->json('features')->nullable();
            $table->json('permissions')->nullable();
            $table->json('hooks')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'category']);
            $table->index(['billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
