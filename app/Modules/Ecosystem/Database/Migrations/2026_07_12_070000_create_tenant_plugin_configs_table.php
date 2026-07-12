<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_plugin_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plugin_id')->constrained('plugins')->cascadeOnDelete();
            $table->text('config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'plugin_id'], 'tenant_plugin_configs_tenant_plugin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_plugin_configs');
    }
};
