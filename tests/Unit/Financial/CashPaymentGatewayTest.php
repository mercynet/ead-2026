<?php

use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Adapters\CashPaymentGateway;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use App\Modules\Financial\Gateways\PaymentGatewayManager;

uses(Tests\TestCase::class);

it('creates a pending manual cash charge without external payment data', function (): void {
    $gateway = new CashPaymentGateway;
    $intent = new ChargeIntent(
        amountCents: 4990,
        currency: 'brl',
        reference: 'ORD-9001',
        idempotencyKey: '3b4e1dc1-0ef6-46d8-9bea-aa992d719744',
        description: 'Curso X',
        metadata: ['order_id' => 42],
    );

    $result = $gateway->charge(['unused_secret' => 'must_not_leak'], $intent);

    expect($gateway->identifier())->toBe('cash')
        ->and($gateway->label())->toBe('Dinheiro')
        ->and($result)->toBeInstanceOf(ChargeResult::class)
        ->and($gateway->confirmationMode())->toBe(PaymentConfirmationMode::Manual)
        ->and($result->status)->toBe(PaymentChargeStatus::Pending)
        ->and($result->externalId)->toBeNull()
        ->and($result->redirectUrl)->toBeNull()
        ->and($result->raw)->toBe([
            'payment_method' => 'cash',
            'payment_flow' => 'manual_confirmation_required',
        ]);
});

it('accepts cash configurations without credentials', function (): void {
    $gateway = new CashPaymentGateway;

    expect($gateway->validateConfiguration([]))->toBeTrue()
        ->and($gateway->validateConfiguration(['anything' => 'accepted']))->toBeTrue();
});

it('validates an empty cash configuration through its schema registry', function (): void {
    $result = app(PaymentGatewayManager::class)->validate('cash', []);

    expect($result->valid)->toBeTrue()
        ->and($result->errors)->toBe([]);
});

it('registers cash gateway in the application payment gateway manager', function (): void {
    $gateway = app(PaymentGatewayManager::class)->get('cash');

    expect($gateway)->toBeInstanceOf(CashPaymentGateway::class)
        ->and($gateway?->identifier())->toBe('cash');
});
