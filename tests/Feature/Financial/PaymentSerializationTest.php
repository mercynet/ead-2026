<?php

use App\Modules\Financial\Models\Payment;
use Illuminate\Support\Carbon;

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
