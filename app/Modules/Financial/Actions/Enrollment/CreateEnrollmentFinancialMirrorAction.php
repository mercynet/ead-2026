<?php

namespace App\Modules\Financial\Actions\Enrollment;

use App\Modules\Financial\Data\EnrollmentFinancialMirrorData;
use App\Modules\Financial\Enums\OrderOriginType;
use App\Modules\Financial\Enums\PaymentChargeState;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Ramsey\Uuid\Uuid;

class CreateEnrollmentFinancialMirrorAction
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function handle(EnrollmentFinancialMirrorData $data): Order
    {
        $sourceKey = 'learning:enrollment:'.$data->enrollmentId;
        $orderNumber = 'ENR-'.$data->enrollmentId;
        $idempotencyKey = Uuid::uuid5(Uuid::NAMESPACE_URL, $sourceKey)->toString();

        try {
            return $this->database->transaction(function () use ($data, $sourceKey, $orderNumber, $idempotencyKey): Order {
                $existing = $this->findByIdentity($data, $orderNumber, $idempotencyKey, true);

                if ($existing !== null) {
                    $this->assertCompleteMirror($existing, $data, $sourceKey, $orderNumber, $idempotencyKey);

                    return $existing;
                }

                $order = new Order;
                $order->fill([
                    'tenant_id' => $data->tenantId,
                    'user_id' => $data->userId,
                    'order_number' => $orderNumber,
                    'status' => 'paid',
                    'origin_type' => OrderOriginType::Direct->value,
                    'subtotal_cents' => 0,
                    'tax_cents' => 0,
                    'total_cents' => 0,
                    'source_key' => $sourceKey,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'enrollment_status' => $data->enrollmentStatus,
                        'source' => $data->source,
                        'billing_type' => $data->billingType,
                        'created_by_instructor_id' => $data->createdByInstructorId,
                        'occurred_at' => $data->occurredAt,
                    ],
                ])->save();

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'itemable_type' => 'course',
                    'itemable_id' => $data->courseId,
                    'item_snapshot' => [
                        'title' => $data->courseTitle,
                        'slug' => $data->courseSlug,
                        'catalog_price_cents' => $data->coursePriceCents,
                        'enrollment_id' => $data->enrollmentId,
                    ],
                    'price_cents' => 0,
                ]);

                Payment::query()->create([
                    'order_id' => $order->id,
                    'status' => 'completed',
                    'gateway_slug' => 'free',
                    'confirmation_mode' => PaymentConfirmationMode::Automatic->value,
                    'charge_state' => PaymentChargeState::Resolved->value,
                ]);

                return $order;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->database->transaction(function () use ($data, $sourceKey, $orderNumber, $idempotencyKey, $exception): Order {
                $existing = $this->findByIdentity($data, $orderNumber, $idempotencyKey, true);

                if ($existing === null) {
                    throw $exception;
                }

                $this->assertCompleteMirror($existing, $data, $sourceKey, $orderNumber, $idempotencyKey);

                return $existing;
            });
        }
    }

    private function findByIdentity(EnrollmentFinancialMirrorData $data, string $orderNumber, string $idempotencyKey, bool $lock): ?Order
    {
        $byOrderNumber = Order::query()
            ->where('tenant_id', $data->tenantId)
            ->where('order_number', $orderNumber);
        $byIdempotencyKey = Order::query()
            ->where('tenant_id', $data->tenantId)
            ->where('user_id', $data->userId)
            ->where('idempotency_key', $idempotencyKey);

        if ($lock) {
            $byOrderNumber->lockForUpdate();
            $byIdempotencyKey->lockForUpdate();
        }

        $order = $byOrderNumber->first();
        $idempotencyOrder = $byIdempotencyKey->first();

        if ($order !== null && $idempotencyOrder !== null && $order->id !== $idempotencyOrder->id) {
            throw new EnrollmentFinancialMirrorIntegrityException('Enrollment financial mirror has conflicting deterministic identities.');
        }

        return $order ?? $idempotencyOrder;
    }

    private function assertCompleteMirror(Order $order, EnrollmentFinancialMirrorData $data, string $sourceKey, string $orderNumber, string $idempotencyKey): void
    {
        $order->load(['items', 'payments']);
        if ($order->items->count() !== 1 || $order->payments->count() !== 1) {
            throw new EnrollmentFinancialMirrorIntegrityException('Enrollment financial mirror is incomplete or has duplicate records.');
        }

        $item = $order->items->firstOrFail();
        $payment = $order->payments->firstOrFail();
        $metadata = $order->metadata ?? [];

        $isValid = $order->tenant_id === $data->tenantId
            && $order->user_id === $data->userId
            && $order->order_number === $orderNumber
            && $order->source_key === $sourceKey
            && $order->idempotency_key === $idempotencyKey
            && $order->status === 'paid'
            && $order->origin_type === OrderOriginType::Direct->value
            && $order->subtotal_cents === 0
            && $order->tax_cents === 0
            && $order->total_cents === 0
            && ($metadata['source'] ?? null) === 'manual'
            && array_key_exists('billing_type', $metadata)
            && $metadata['billing_type'] === null
            && $item->itemable_type === 'course'
            && $item->itemable_id === $data->courseId
            && $item->price_cents === 0
            && ($item->item_snapshot['title'] ?? null) === $data->courseTitle
            && ($item->item_snapshot['slug'] ?? null) === $data->courseSlug
            && ($item->item_snapshot['catalog_price_cents'] ?? null) === $data->coursePriceCents
            && ($item->item_snapshot['enrollment_id'] ?? null) === $data->enrollmentId
            && $payment->status === 'completed'
            && $payment->gateway_slug === 'free'
            && $payment->confirmation_mode === PaymentConfirmationMode::Automatic->value
            && $payment->charge_state === PaymentChargeState::Resolved->value
            && $payment->tenant_plugin_config_id === null
            && $payment->gateway_configuration_version === null
            && $payment->psp_idempotency_key === null
            && $payment->external_id === null
            && $payment->gateway_response === null
            && $payment->charge_claim_token === null
            && $payment->charge_claimed_at === null;

        if (! $isValid) {
            throw new EnrollmentFinancialMirrorIntegrityException('Enrollment financial mirror is incomplete or does not match its enrollment identity.');
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000' && ($exception->errorInfo[1] ?? null) === 1062;
    }
}
