<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('plugin_name');
            $table->enum('status', ['active', 'suspended', 'cancelled', 'expired'])->default('active');
            $table->unsignedInteger('price_cents');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_billed_at')->nullable();
            $table->timestamp('next_billing_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('plugin_name')->references('name')->on('plugins')->cascadeOnDelete();
            $table->unique(['tenant_id', 'plugin_name']);
            $table->index(['tenant_id', 'status']);
            $table->index(['next_billing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_subscriptions');
    }
};
