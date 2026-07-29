<?php

use App\Modules\Financial\Support\HistoricalPaymentChargeStateClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_plugin_config_id')->nullable()->after('order_id');
            $table->string('gateway_configuration_version', 64)->nullable()->after('tenant_plugin_config_id');
            $table->string('psp_idempotency_key', 120)->nullable()->after('external_id');
            $table->string('charge_state', 20)->nullable()->after('status');
            $table->uuid('charge_claim_token')->nullable()->after('charge_state');
            $table->timestamp('charge_claimed_at')->nullable()->after('charge_claim_token');
            $table->index(['tenant_plugin_config_id', 'gateway_configuration_version'], 'payments_gateway_ownership_index');
            $table->unique('psp_idempotency_key', 'payments_psp_idempotency_key_unique');
        });
        DB::table('payments')->whereNull('charge_state')->orderBy('id')->chunkById(100, function ($payments): void {
            foreach ($payments as $payment) {
                DB::table('payments')->where('id', $payment->id)->update([
                    'charge_state' => HistoricalPaymentChargeStateClassifier::classify(
                        $payment->status,
                        $payment->gateway_response,
                        $payment->external_id,
                    ),
                ]);
            }
        });
        if (DB::table('payments')->whereNull('charge_state')->exists()) {
            throw new RuntimeException('Payment charge state backfill did not complete.');
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('charge_state', 20)->default('created')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_psp_idempotency_key_unique');
            $table->dropIndex('payments_gateway_ownership_index');
            $table->dropColumn(['tenant_plugin_config_id', 'gateway_configuration_version', 'psp_idempotency_key', 'charge_state', 'charge_claim_token', 'charge_claimed_at']);
        });
    }
};
