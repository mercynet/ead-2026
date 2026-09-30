<?php

namespace App\Modules\Financial\Jobs;

use App\Modules\Financial\Enums\PaymentChargeState;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Events\OrderPaidEvent;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderPaidOutbox;
use App\Modules\Financial\Models\Payment;
use App\Modules\Financial\Services\Outbox\OrderPaidOutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $paymentId,
        public readonly string $gatewaySlug,
        public readonly string $status,
        public readonly ?string $externalId = null,
        public readonly ?string $orderNumber = null,
    ) {}

    public function handle(DatabaseManager $database, OrderPaidOutboxService $outbox): void
    {
        $paidOutbox = $database->transaction(function () use ($outbox): ?OrderPaidOutbox {
            $payment = Payment::query()->whereKey($this->paymentId)->lockForUpdate()->first();

            if ($payment === null
                || $payment->gateway_slug !== $this->gatewaySlug
                || $payment->confirmation_mode !== PaymentConfirmationMode::Automatic->value) {
                return null;
            }

            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();
            if ($order === null || ($this->orderNumber !== null && $order->order_number !== $this->orderNumber)) {
                return null;
            }

            if ($this->externalId !== null && $payment->external_id !== $this->externalId) {
                return null;
            }

            if ($this->status === 'failed') {
                $this->markFailed($order, $payment);

                return null;
            }

            if ($this->status !== 'paid') {
                return null;
            }

            if ($order->status === 'paid' && $payment->status === 'completed') {
                $items = $this->lockedItems($order);

                return $outbox->record($this->orderPaidEvent($order, $items));
            }

            if ($order->status !== 'pending' || $payment->status !== 'pending') {
                return null;
            }

            $payment->fill([
                'status' => 'completed',
                'external_id' => $this->externalId ?? $payment->external_id,
                'gateway_response' => [
                    'source' => 'webhook',
                    'status' => 'paid',
                    'external_id' => $this->externalId ?? $payment->external_id,
                ],
                'charge_state' => PaymentChargeState::Resolved->value,
                'charge_claim_token' => null,
                'charge_claimed_at' => null,
            ])->save();
            $order->update(['status' => 'paid']);

            $items = $this->lockedItems($order);

            return $outbox->record($this->orderPaidEvent($order, $items));
        });

        if ($paidOutbox === null) {
            return;
        }

        try {
            $outbox->publish($paidOutbox->id);
        } catch (\Throwable $exception) {
            Log::warning('OrderPaid outbox publish failed.', [
                'order_id' => $paidOutbox->order_id,
                'outbox_id' => $paidOutbox->id,
                'exception_class' => $exception::class,
            ]);
        }
    }

    private function markFailed(Order $order, Payment $payment): void
    {
        if ($order->status !== 'pending' || $payment->status !== 'pending') {
            return;
        }

        $payment->fill([
            'status' => 'failed',
            'external_id' => $this->externalId ?? $payment->external_id,
            'gateway_response' => [
                'source' => 'webhook',
                'status' => 'failed',
                'external_id' => $this->externalId ?? $payment->external_id,
            ],
            'charge_state' => PaymentChargeState::Resolved->value,
            'charge_claim_token' => null,
            'charge_claimed_at' => null,
        ])->save();
        $order->update(['status' => 'failed']);
    }

    /** @return Collection<int, \App\Modules\Financial\Models\OrderItem> */
    private function lockedItems(Order $order): Collection
    {
        return $order->items()
            ->lockForUpdate()
            ->get(['itemable_type', 'itemable_id', 'item_snapshot', 'price_cents']);
    }

    /** @param  Collection<int, \App\Modules\Financial\Models\OrderItem>  $items */
    private function orderPaidEvent(Order $order, Collection $items): OrderPaidEvent
    {
        return new OrderPaidEvent(
            orderId: $order->id,
            tenantId: $order->tenant_id,
            userId: $order->user_id,
            paidAt: $order->updated_at->toIso8601String(),
            items: $items->map(fn ($item): array => [
                'itemable_type' => $item->itemable_type,
                'itemable_id' => $item->itemable_id,
                'item_snapshot' => $item->item_snapshot,
                'price_cents' => $item->price_cents,
            ])->all(),
        );
    }
}
