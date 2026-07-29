<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('gateway_slug', 100)->nullable()->after('status');
            $table->string('confirmation_mode', 30)->nullable()->after('gateway_slug');
            $table->index(['order_id', 'status', 'gateway_slug', 'confirmation_mode'], 'payments_order_classification_index');
            $table->unique(['gateway_slug', 'external_id'], 'payments_gateway_external_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_order_classification_index');
            $table->dropUnique('payments_gateway_external_id_unique');
            $table->dropColumn(['gateway_slug', 'confirmation_mode']);
        });
    }
};
