<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->morphs('itemable');
            $table->json('item_snapshot');
            $table->unsignedInteger('price_cents');
            $table->timestamps();

            $table->index(['order_id', 'itemable_type', 'itemable_id'], 'order_items_order_itemable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
