<?php

use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\Payment;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('retries mixed payloads and restores JSON through a complete migration cycle', function (): void {
    $order = Order::factory()->create([
        'metadata' => ['migration_secret' => 'order-secret'],
    ]);
    $payment = Payment::factory()->for($order)->create([
        'gateway_response' => ['migration_secret' => 'gateway-secret'],
        'metadata' => ['migration_secret' => 'payment-secret'],
    ]);
    $migration = require base_path('app/Modules/Financial/Database/Migrations/2026_09_30_120000_encrypt_financial_sensitive_payloads.php');
    $rolledBack = false;

    try {
        $migration->down();
        $rolledBack = true;

        expect(Schema::getColumnType('orders', 'metadata'))->toBe('json')
            ->and(Schema::getColumnType('payments', 'gateway_response'))->toBe('json')
            ->and(DB::table('orders')->where('id', $order->id)->value('metadata'))->toContain('order-secret')
            ->and(DB::table('payments')->where('id', $payment->id)->value('gateway_response'))->toContain('gateway-secret');

        Schema::table('orders', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->text('metadata')->nullable()->change();
        });
        Schema::table('payments', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->text('gateway_response')->nullable()->change();
            $table->text('metadata')->nullable()->change();
        });
        DB::table('orders')->where('id', $order->id)->update([
            'metadata' => Crypt::encryptString(json_encode(['migration_secret' => 'order-secret'], JSON_THROW_ON_ERROR)),
        ]);

        $migration->up();
        $rolledBack = false;

        expect(Schema::getColumnType('orders', 'metadata'))->toBe('text')
            ->and(Schema::getColumnType('payments', 'gateway_response'))->toBe('text')
            ->and(DB::table('orders')->where('id', $order->id)->value('metadata'))->not->toContain('order-secret')
            ->and(DB::table('payments')->where('id', $payment->id)->value('gateway_response'))->not->toContain('gateway-secret')
            ->and($order->fresh()->metadata)->toBe(['migration_secret' => 'order-secret'])
            ->and($payment->fresh()->gateway_response)->toBe(['migration_secret' => 'gateway-secret']);

        $migration->down();
        $rolledBack = true;

        expect(Schema::getColumnType('orders', 'metadata'))->toBe('json')
            ->and(Schema::getColumnType('payments', 'gateway_response'))->toBe('json')
            ->and($order->fresh()->metadata)->toBe(['migration_secret' => 'order-secret'])
            ->and($payment->fresh()->metadata)->toBe(['migration_secret' => 'payment-secret']);
    } finally {
        if ($rolledBack) {
            $migration->up();
        }

        DB::table('payments')->where('id', $payment->id)->delete();
        DB::table('orders')->where('id', $order->id)->delete();
    }
});
