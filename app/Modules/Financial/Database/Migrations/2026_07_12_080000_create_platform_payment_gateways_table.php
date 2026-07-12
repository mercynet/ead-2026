<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_slug', 50)->unique();
            $table->text('configuration');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'is_default'], 'platform_payment_gateways_active_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_gateways');
    }
};
