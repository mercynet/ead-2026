<?php

use App\Modules\Ecosystem\Contracts\ActiveGateway;
use App\Modules\Financial\Gateways\Adapters\CashPaymentGateway;
use App\Modules\Financial\Gateways\Data\ResolvedGateway;

uses(Tests\TestCase::class);

it('rejects invalid immutable gateway ownership identities', function (): void {
    expect(fn () => new ActiveGateway('cash', [], 0, 'v1'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new ActiveGateway('cash', [], 1, ''))->toThrow(InvalidArgumentException::class);
    expect(fn () => new ResolvedGateway(new CashPaymentGateway, [], -1, 'v1'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new ResolvedGateway(new CashPaymentGateway, [], 1, ''))->toThrow(InvalidArgumentException::class);
});
