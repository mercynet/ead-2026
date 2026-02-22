<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('plugin_name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('context')->nullable();
            $table->timestamp('executed_at')->useCurrent();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('plugin_name')->references('name')->on('plugins')->cascadeOnDelete();
            $table->index(['tenant_id', 'plugin_name']);
            $table->index(['tenant_id', 'executed_at']);
            $table->index(['executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_usage_logs');
    }
};
