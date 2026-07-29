<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->after('source_key');
            $table->unique(['tenant_id', 'user_id', 'idempotency_key'], 'orders_tenant_user_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_tenant_user_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
