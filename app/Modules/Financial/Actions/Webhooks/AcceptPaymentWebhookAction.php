<?php

namespace App\Modules\Financial\Actions\Webhooks;

use App\Modules\Core\Models\Tenant;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\TenantGatewayResolver;
use App\Modules\Financial\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Financial\Models\Payment;
use App\Modules\Financial\Services\Webhooks\WebhookSignatureVerifier;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AcceptPaymentWebhookAction
{
    public function __construct(
        private readonly TenantGatewayResolver $gatewayResolver,
        private readonly WebhookSignatureVerifier $signatureVerifier,
        private readonly Dispatcher $dispatcher,
    ) {}

    /**
     * @param  array{status: string, order_number?: string, external_id?: string}  $payload
     */
    public function handle(
        string $gatewaySlug,
        string $rawPayload,
        array $payload,
        ?string $signature,
    ): void {
        $payment = $this->payment($gatewaySlug, $payload);
        if ($payment === null || $payment->order === null) {
            throw $this->invalidWebhook();
        }

        $tenant = $payment->order->tenant;
        if (! $tenant instanceof Tenant) {
            throw $this->invalidWebhook();
        }

        try {
            $gateway = $this->gatewayResolver->resolveExact(
                $tenant,
                $payment->tenant_plugin_config_id,
                $payment->gateway_configuration_version,
                $gatewaySlug,
            );
        } catch (\Throwable) {
            throw $this->invalidWebhook();
        }

        if ($gateway->confirmationMode() !== PaymentConfirmationMode::Automatic) {
            throw $this->invalidWebhook();
        }

        try {
            $valid = $this->signatureVerifier->verify(
                $gateway->adapter,
                $gateway->credentials,
                $rawPayload,
                $signature,
            );
        } catch (\Throwable) {
            $valid = false;
        }

        if (! $valid) {
            throw $this->invalidWebhook();
        }

        $this->dispatcher->dispatch(new ProcessPaymentWebhookJob(
            paymentId: $payment->id,
            gatewaySlug: $gatewaySlug,
            status: $payload['status'],
            externalId: $payload['external_id'] ?? null,
            orderNumber: $payload['order_number'] ?? null,
        ));
    }

    /**
     * @param  array{status: string, order_number?: string, external_id?: string}  $payload
     */
    private function payment(string $gatewaySlug, array $payload): ?Payment
    {
        $query = Payment::query()
            ->with('order.tenant')
            ->where('gateway_slug', $gatewaySlug);

        if (isset($payload['external_id'])) {
            $query->where('external_id', $payload['external_id']);
        }

        if (isset($payload['order_number'])) {
            $query->whereHas('order', fn (Builder $order): Builder => $order->where('order_number', $payload['order_number']));
        }

        $payments = $query->limit(2)->get();

        return $payments->count() === 1 ? $payments->first() : null;
    }

    private function invalidWebhook(): ValidationException
    {
        return ValidationException::withMessages(['webhook' => 'Webhook inválido.']);
    }
}
