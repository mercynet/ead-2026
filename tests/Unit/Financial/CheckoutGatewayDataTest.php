<?php

use App\Modules\Financial\Enums\OrderOriginType;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;

uses(Tests\TestCase::class);

it('keeps normalized checkout gateway values typed and lowercase', function (): void {
    $intent = new ChargeIntent(12900, 'brl', 'ORD-1', '3b4e1dc1-0ef6-46d8-9bea-aa992d719744');
    $result = new ChargeResult(PaymentChargeStatus::Pending, clientSecret: 'cs_test');

    expect(OrderOriginType::Direct->value)->toBe('direct')
        ->and(PaymentConfirmationMode::Automatic->value)->toBe('automatic')
        ->and($intent->idempotencyKey)->toBe('3b4e1dc1-0ef6-46d8-9bea-aa992d719744')
        ->and($result->status)->toBe(PaymentChargeStatus::Pending)
        ->and($result->clientSecret)->toBe('cs_test');
});
