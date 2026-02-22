<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_billing', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('plugin_name');
            $table->foreignId('subscription_id')->constrained('plugin_subscriptions')->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->timestamp('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('payment_details')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->foreign('plugin_name')->references('name')->on('plugins')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['subscription_id', 'status']);
            $table->index(['due_date']);
            $table->index(['next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_billing');
    }
};
