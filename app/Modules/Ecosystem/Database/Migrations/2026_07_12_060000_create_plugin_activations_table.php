<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plugin_id')->constrained('plugins')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'plugin_id'], 'plugin_activations_tenant_plugin_unique');
            $table->index(['tenant_id', 'status'], 'plugin_activations_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_activations');
    }
};
