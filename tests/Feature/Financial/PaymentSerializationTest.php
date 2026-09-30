<?php

use App\Modules\Financial\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('hides payment PSP and gateway internals while preserving ownership and charge state', function (): void {
    $claimedAt = Carbon::parse('2026-07-28 14:30:00');
    $payment = Payment::factory()->create([
        'gateway_response' => ['raw' => 'gateway-response'],
        'metadata' => ['secret' => 'payment-metadata'],
        'psp_idempotency_key' => 'psp-secret-key',
        'charge_claim_token' => 'claim-secret-token',
        'charge_claimed_at' => $claimedAt,
        'tenant_plugin_config_id' => 77,
        'gateway_configuration_version' => 'gateway-version-77',
        'charge_state' => 'processing',
    ])->fresh();

    expect($payment->toArray())->not->toHaveKeys(['gateway_response', 'metadata', 'psp_idempotency_key', 'charge_claim_token'])
        ->and($payment->charge_claimed_at)->toBeInstanceOf(Carbon::class)
        ->and($payment->charge_claimed_at->equalTo($claimedAt))->toBeTrue()
        ->and($payment->charge_state)->toBe('processing')
        ->and($payment->tenant_plugin_config_id)->toBe(77)
        ->and($payment->gateway_configuration_version)->toBe('gateway-version-77');
});

it('encrypts financial metadata at rest while keeping it available to domain code', function (): void {
    $payment = Payment::factory()->create([
        'gateway_response' => ['raw' => 'gateway-response-secret'],
        'metadata' => ['client_secret' => 'client-secret-at-rest'],
    ]);
    $payment->order->update(['metadata' => ['internal_secret' => 'order-secret-at-rest']]);

    $storedPayment = DB::table('payments')->where('id', $payment->id)->first();
    $storedOrder = DB::table('orders')->where('id', $payment->order_id)->first();

    expect($storedPayment->gateway_response)->not->toContain('gateway-response-secret')
        ->and($storedPayment->metadata)->not->toContain('client-secret-at-rest')
        ->and($storedOrder->metadata)->not->toContain('order-secret-at-rest')
        ->and($payment->fresh()->gateway_response)->toBe(['raw' => 'gateway-response-secret'])
        ->and($payment->fresh()->metadata)->toBe(['client_secret' => 'client-secret-at-rest'])
        ->and($payment->order->fresh()->metadata)->toBe(['internal_secret' => 'order-secret-at-rest']);
});
