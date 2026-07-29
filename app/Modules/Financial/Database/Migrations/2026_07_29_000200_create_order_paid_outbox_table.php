<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_paid_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->json('payload');
            $table->uuid('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_error_class', 255)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'event_type'], 'order_paid_outbox_logical_unique');
            $table->index(['dispatched_at', 'claimed_at'], 'order_paid_outbox_pending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_paid_outbox');
    }
};
