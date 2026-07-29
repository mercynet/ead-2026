<?php

namespace App\Modules\Financial\Actions\Admin;

use App\Modules\Financial\Events\OrderPaidEvent;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\Payment;
use App\Modules\Financial\Services\Outbox\OrderPaidOutboxService;
use App\Shared\Http\ApiContext;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConfirmManualPaymentAction
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly OrderPaidOutboxService $outbox,
    ) {}

    public function handle(ApiContext $context, int $orderId): Order
    {
        [$order, $outbox] = $this->database->transaction(function () use ($context, $orderId): array {
            $order = Order::query()
                ->whereKey($orderId)
                ->whereBelongsTo($context->requiredTenant(), 'tenant')
                ->lockForUpdate()
                ->firstOrFail();
            $payments = $order->payments()->lockForUpdate()->get();
            $manualCashPayments = $this->authoritativeManualCashPayments($payments);

            if ($order->status === 'paid') {
                if ($manualCashPayments->count() !== 1) {
                    throw ValidationException::withMessages(['order' => 'Pedido pago não possui pagamento manual em dinheiro confirmado.']);
                }

                /** @var Payment $manualCashPayment */
                $manualCashPayment = $manualCashPayments->first();

                if ($manualCashPayment->status !== 'completed') {
                    throw ValidationException::withMessages(['order' => 'Pedido pago não possui pagamento manual em dinheiro confirmado.']);
                }

                $items = $this->lockedItems($order);
                $order->setRelation('items', $items);

                return [$order, $this->outbox->record($this->orderPaidEvent($order, $items))];
            }

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages(['order' => 'Somente pedidos pendentes podem ter pagamento manual confirmado.']);
            }

            if ($manualCashPayments->count() !== 1) {
                throw ValidationException::withMessages(['payment' => 'Pedido deve possuir exatamente um pagamento manual em dinheiro pendente.']);
            }

            /** @var Payment $pendingCashPayment */
            $pendingCashPayment = $manualCashPayments->first();

            if ($pendingCashPayment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Pedido deve possuir exatamente um pagamento manual em dinheiro pendente.']);
            }

            $pendingCashPayment->fill([
                'status' => 'completed',
                'charge_state' => 'resolved',
                'charge_claim_token' => null,
                'charge_claimed_at' => null,
            ])->save();
            $order->fill(['status' => 'paid'])->save();
            $items = $this->lockedItems($order);
            $order->setRelation('items', $items);

            return [$order, $this->outbox->record($this->orderPaidEvent($order, $items))];
        });

        try {
            $this->outbox->publish($outbox->id);
        } catch (Throwable $exception) {
            Log::warning('OrderPaid outbox publish failed.', [
                'order_id' => $order->id,
                'outbox_id' => $outbox->id,
                'exception_class' => $exception::class,
            ]);
        }

        return $order;
    }

    /** @return Collection<int, \App\Modules\Financial\Models\OrderItem> */
    private function lockedItems(Order $order): Collection
    {
        return $order->items()
            ->lockForUpdate()
            ->get(['itemable_type', 'itemable_id', 'item_snapshot', 'price_cents']);
    }

    /** @param Collection<int, \App\Modules\Financial\Models\OrderItem> $items */
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

    /** @return Collection<int, Payment> */
    private function authoritativeManualCashPayments(Collection $payments): Collection
    {
        return $payments->filter(fn (Payment $payment): bool => $payment->gateway_slug === 'cash'
            && $payment->confirmation_mode === 'manual');
    }
}
