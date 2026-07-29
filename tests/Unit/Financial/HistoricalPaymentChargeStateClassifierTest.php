<?php

use App\Modules\Financial\Support\HistoricalPaymentChargeStateClassifier;

it('classifies historical payment records for migration backfill', function (?string $status, mixed $gatewayResponse, ?string $externalId, string $expected): void {
    expect(HistoricalPaymentChargeStateClassifier::classify($status, $gatewayResponse, $externalId))->toBe($expected);
})->with([
    'completed payment' => ['completed', null, null, 'resolved'],
    'failed payment' => ['failed', null, null, 'resolved'],
    'gateway response present' => ['pending', ['raw' => 'response'], null, 'resolved'],
    'external id present' => ['pending', null, 'ch_historical', 'resolved'],
    'ambiguous pending payment' => ['pending', null, null, 'unknown'],
]);
