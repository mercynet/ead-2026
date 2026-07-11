<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_number', 50);
            $table->string('status', 30)->default('pending');
            $table->string('origin_type', 50);
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('source_key', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number'], 'orders_tenant_order_number_unique');
            $table->index(['tenant_id', 'user_id'], 'orders_tenant_user_index');
            $table->index(['tenant_id', 'status'], 'orders_tenant_status_index');
            $table->index(['tenant_id', 'source_key'], 'orders_tenant_source_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
