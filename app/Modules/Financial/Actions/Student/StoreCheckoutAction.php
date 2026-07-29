<?php

namespace App\Modules\Financial\Actions\Student;

use App\Modules\Financial\Enums\OrderOriginType;
use App\Modules\Financial\Enums\PaymentChargeState;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Events\OrderPaidEvent;
use App\Modules\Financial\Exceptions\CheckoutConflictException;
use App\Modules\Financial\Exceptions\GatewayUnavailableException;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use App\Modules\Financial\Gateways\Data\ResolvedGateway;
use App\Modules\Financial\Gateways\TenantGatewayResolver;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;
use App\Modules\Financial\Services\Outbox\OrderPaidOutboxService;
use App\Modules\Learning\Contracts\CourseCheckoutCatalog;
use App\Modules\Learning\Contracts\CourseCheckoutOffering;
use App\Shared\Http\ApiContext;
use Illuminate\Database\DatabaseManager;
use Throwable;

class StoreCheckoutAction
{
    private const PROCESSING_TIMEOUT_MINUTES = 5;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CourseCheckoutCatalog $catalog,
        private readonly TenantGatewayResolver $gatewayResolver,
        private readonly OrderPaidOutboxService $outbox,
    ) {}

    public function handle(ApiContext $context, int $courseId, string $idempotencyKey): Order
    {
        $offering = $this->offering($context, $courseId);
        $claim = $this->claim($context, $offering, $idempotencyKey);

        if ($claim['payment']->charge_state === PaymentChargeState::Resolved->value) {
            if ($claim['order']->status === 'paid' && $claim['created']) {
                $this->publishPaid($claim['order']);
            }

            return $this->responseOrder($claim['order'], $claim['created']);
        }

        $payment = $claim['payment'];
        $token = $claim['token'];

        try {
            $gateway = $this->gatewayResolver->resolveExact(
                $context->requiredTenant(),
                $payment->tenant_plugin_config_id,
                $payment->gateway_configuration_version,
                $payment->gateway_slug,
            );
        } catch (Throwable) {
            $this->releaseClaim($payment, $token);
            throw new GatewayUnavailableException('Gateway de pagamento indisponível.');
        }

        try {
            $result = $gateway->charge(new ChargeIntent(
                amountCents: $claim['order']->total_cents,
                currency: 'brl',
                reference: $claim['order']->order_number,
                idempotencyKey: $payment->psp_idempotency_key,
                description: 'Compra de curso',
            ));
            if ($gateway->confirmationMode() === PaymentConfirmationMode::Manual && $result->status !== PaymentChargeStatus::Pending) {
                throw new \UnexpectedValueException('Manual gateway returned an invalid charge result.');
            }
        } catch (Throwable) {
            $this->markUnknown($payment, $token);
            throw new GatewayUnavailableException('Gateway de pagamento indisponível.');
        }

        try {
            [$order, $paid] = $this->persistResult($claim['order']->id, $payment, $token, $result);
        } catch (CheckoutConflictException $exception) {
            throw $exception;
        } catch (Throwable) {
            $this->markUnknown($payment, $token);
            throw new GatewayUnavailableException('Gateway de pagamento indisponível.');
        }

        if ($paid) {
            $this->publishPaid($order);
        }

        return $this->responseOrder($order, $claim['created']);
    }

    /** @return array{order: Order, payment: Payment, token: string|null, created: bool} */
    private function claim(ApiContext $context, CourseCheckoutOffering $offering, string $idempotencyKey): array
    {
        $claim = $this->database->transaction(function () use ($context, $offering, $idempotencyKey): array|CheckoutConflictException {
            $existing = Order::query()->whereBelongsTo($context->requiredTenant(), 'tenant')->whereBelongsTo($context->requiredUser(), 'user')
                ->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->source_key !== $offering->purchaseCycleKey) {
                    throw new CheckoutConflictException('idempotency_conflict', 'Chave de idempotência já usada para outra compra.');
                }

                $payment = $existing->payments()->lockForUpdate()->firstOrFail();
                if ($payment->charge_state === PaymentChargeState::Resolved->value) {
                    return ['order' => $existing, 'payment' => $payment, 'token' => null, 'created' => false];
                }
                if ($payment->charge_state === PaymentChargeState::Unknown->value) {
                    throw new CheckoutConflictException('payment_reconciliation_required', 'Pagamento requer reconciliação.');
                }
                if ($payment->charge_state === PaymentChargeState::Processing->value) {
                    if ($payment->charge_claimed_at !== null && $payment->charge_claimed_at->gt(now()->subMinutes(self::PROCESSING_TIMEOUT_MINUTES))) {
                        throw new CheckoutConflictException('checkout_in_progress', 'Checkout em processamento.');
                    }
                    $payment->fill(['charge_state' => PaymentChargeState::Unknown->value, 'charge_claim_token' => null, 'charge_claimed_at' => null])->save();

                    return new CheckoutConflictException('payment_reconciliation_required', 'Pagamento requer reconciliação.');
                }
                if ($payment->charge_state !== PaymentChargeState::Created->value) {
                    throw new CheckoutConflictException('payment_reconciliation_required', 'Pagamento requer reconciliação.');
                }

                return $this->claimPayment($existing, $payment, false);
            }

            if (! $offering->isEligible) {
                throw new CheckoutConflictException('already_enrolled', 'Você já possui matrícula atual neste curso.');
            }

            $duplicate = Order::query()->whereBelongsTo($context->requiredTenant(), 'tenant')->whereBelongsTo($context->requiredUser(), 'user')
                ->where('source_key', $offering->purchaseCycleKey)->whereIn('status', ['pending', 'paid'])->lockForUpdate()->exists();
            if ($duplicate) {
                throw new CheckoutConflictException('checkout_already_exists', 'Já existe checkout ativo para esta compra.');
            }

            $gateway = null;
            if ($offering->priceCents > 0) {
                try {
                    $gateway = $this->gatewayResolver->resolve($context->requiredTenant());
                } catch (Throwable) {
                    throw new GatewayUnavailableException('Gateway de pagamento indisponível.');
                }
            }

            $order = $this->createOrder($context, $offering, $idempotencyKey, $gateway);
            $payment = $order->payments()->firstOrFail();
            if ($offering->priceCents === 0) {
                $this->outbox->record($this->orderPaidEvent($order));

                return ['order' => $order, 'payment' => $payment, 'token' => null, 'created' => true];
            }

            return $this->claimPayment($order, $payment, true);
        });

        if ($claim instanceof CheckoutConflictException) {
            throw $claim;
        }

        return $claim;
    }

    /** @return array{order: Order, payment: Payment, token: string, created: bool} */
    private function claimPayment(Order $order, Payment $payment, bool $created): array
    {
        $token = (string) str()->uuid();
        $payment->fill(['charge_state' => PaymentChargeState::Processing->value, 'charge_claim_token' => $token, 'charge_claimed_at' => now()])->save();

        return ['order' => $order, 'payment' => $payment, 'token' => $token, 'created' => $created];
    }

    private function createOrder(ApiContext $context, CourseCheckoutOffering $offering, string $idempotencyKey, ?ResolvedGateway $gateway): Order
    {
        $order = new Order;
        $order->fill([
            'tenant_id' => $context->requiredTenant()->id, 'user_id' => $context->requiredUser()->id,
            'order_number' => sprintf('ORD-%s', strtoupper(str()->ulid())), 'status' => $offering->priceCents === 0 ? 'paid' : 'pending',
            'origin_type' => OrderOriginType::Direct->value, 'subtotal_cents' => $offering->priceCents, 'tax_cents' => 0,
            'total_cents' => $offering->priceCents, 'source_key' => $offering->purchaseCycleKey, 'idempotency_key' => $idempotencyKey,
        ])->save();
        OrderItem::query()->create(['order_id' => $order->id, 'itemable_type' => $offering->type, 'itemable_id' => $offering->courseId, 'item_snapshot' => $offering->snapshot, 'price_cents' => $offering->priceCents]);
        Payment::query()->create([
            'order_id' => $order->id, 'status' => $offering->priceCents === 0 ? 'completed' : 'pending',
            'gateway_slug' => $offering->priceCents === 0 ? 'free' : $gateway?->slug(),
            'confirmation_mode' => $offering->priceCents === 0 ? PaymentConfirmationMode::Automatic->value : $gateway?->confirmationMode()->value,
            'tenant_plugin_config_id' => $offering->priceCents === 0 ? null : $gateway?->tenantPluginConfigId,
            'gateway_configuration_version' => $offering->priceCents === 0 ? null : $gateway?->configurationVersion,
            'psp_idempotency_key' => $offering->priceCents === 0 ? null : 'sale-order:'.$order->order_number,
            'charge_state' => $offering->priceCents === 0 ? PaymentChargeState::Resolved->value : PaymentChargeState::Created->value,
        ]);

        return $order;
    }

    /** @return array{0: Order, 1: bool} */
    private function persistResult(int $orderId, Payment $identity, string $token, ChargeResult $result): array
    {
        return $this->database->transaction(function () use ($orderId, $identity, $token, $result): array {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $payment = $order->payments()->whereKey($identity->id)->lockForUpdate()->firstOrFail();
            if ($payment->charge_state === PaymentChargeState::Resolved->value) {
                return [$order, false];
            }
            if (! $this->ownsClaim($payment, $identity, $token)) {
                throw new CheckoutConflictException('payment_reconciliation_required', 'Pagamento requer reconciliação.');
            }

            $payment->fill([
                'status' => match ($result->status) {
                    PaymentChargeStatus::Paid => 'completed', PaymentChargeStatus::Failed => 'failed', PaymentChargeStatus::Pending => 'pending'
                },
                'external_id' => $result->externalId, 'gateway_response' => $result->raw,
                'metadata' => array_filter(['redirect_url' => $result->redirectUrl, 'client_secret' => $result->clientSecret]),
                'charge_state' => PaymentChargeState::Resolved->value, 'charge_claim_token' => null, 'charge_claimed_at' => null,
            ])->save();
            $paid = $result->status === PaymentChargeStatus::Paid;
            if ($paid) {
                $order->update(['status' => 'paid']);
                $this->outbox->record($this->orderPaidEvent($order));
            } elseif ($result->status === PaymentChargeStatus::Failed) {
                $order->update(['status' => 'failed']);
            }

            return [$order, $paid];
        });
    }

    private function ownsClaim(Payment $payment, Payment $identity, string $token): bool
    {
        return $payment->charge_state === PaymentChargeState::Processing->value
            && $payment->charge_claim_token === $token
            && $payment->tenant_plugin_config_id === $identity->tenant_plugin_config_id
            && $payment->gateway_configuration_version === $identity->gateway_configuration_version
            && $payment->gateway_slug === $identity->gateway_slug
            && $payment->psp_idempotency_key === $identity->psp_idempotency_key;
    }

    private function releaseClaim(Payment $payment, string $token): void
    {
        Payment::query()->whereKey($payment->id)->where('charge_state', PaymentChargeState::Processing->value)->where('charge_claim_token', $token)
            ->where('tenant_plugin_config_id', $payment->tenant_plugin_config_id)->where('gateway_configuration_version', $payment->gateway_configuration_version)
            ->where('gateway_slug', $payment->gateway_slug)->where('psp_idempotency_key', $payment->psp_idempotency_key)
            ->update(['charge_state' => PaymentChargeState::Created->value, 'charge_claim_token' => null, 'charge_claimed_at' => null]);
    }

    private function markUnknown(Payment $payment, string $token): void
    {
        Payment::query()->whereKey($payment->id)->where('charge_state', PaymentChargeState::Processing->value)->where('charge_claim_token', $token)
            ->where('tenant_plugin_config_id', $payment->tenant_plugin_config_id)->where('gateway_configuration_version', $payment->gateway_configuration_version)
            ->where('gateway_slug', $payment->gateway_slug)->where('psp_idempotency_key', $payment->psp_idempotency_key)
            ->update(['charge_state' => PaymentChargeState::Unknown->value, 'charge_claim_token' => null, 'charge_claimed_at' => null]);
    }

    private function publishPaid(Order $order): void
    {
        $outbox = $this->outbox->record($this->orderPaidEvent($order));
        try {
            $this->outbox->publish($outbox->id);
        } catch (Throwable) {
        }
    }

    private function orderPaidEvent(Order $order): OrderPaidEvent
    {
        return new OrderPaidEvent($order->id, $order->tenant_id, $order->user_id, $order->updated_at->toIso8601String(), $order->items()->get(['itemable_type', 'itemable_id', 'item_snapshot', 'price_cents'])->map->only(['itemable_type', 'itemable_id', 'item_snapshot', 'price_cents'])->all());
    }

    private function responseOrder(Order $order, bool $created): Order
    {
        $order->load(['items', 'payments']);
        $order->wasRecentlyCreated = $created;

        return $order;
    }

    private function offering(ApiContext $context, int $courseId): CourseCheckoutOffering
    {
        return $this->catalog->resolve($context->requiredTenant()->id, $context->requiredUser()->id, $courseId);
    }
}
