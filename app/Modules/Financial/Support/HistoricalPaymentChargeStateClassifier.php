<?php

namespace App\Modules\Financial\Support;

class HistoricalPaymentChargeStateClassifier
{
    public static function classify(?string $status, mixed $gatewayResponse, ?string $externalId): string
    {
        if (in_array($status, ['completed', 'failed'], true) || $gatewayResponse !== null || $externalId !== null) {
            return 'resolved';
        }

        return 'unknown';
    }
}
