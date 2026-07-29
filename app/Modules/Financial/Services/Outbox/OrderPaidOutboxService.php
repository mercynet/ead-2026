<?php

namespace App\Modules\Financial\Services\Outbox;

use App\Modules\Financial\Events\OrderPaidEvent;
use App\Modules\Financial\Models\OrderPaidOutbox;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderPaidOutboxService
{
    public function __construct(private readonly DatabaseManager $database, private readonly Dispatcher $events) {}

    public function record(OrderPaidEvent $event): OrderPaidOutbox
    {
        $identity = ['order_id' => $event->orderId, 'event_type' => OrderPaidEvent::class];
        try {
            return OrderPaidOutbox::query()->firstOrCreate($identity, ['payload' => $this->payload($event)]);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return OrderPaidOutbox::query()->where($identity)->firstOrFail();
        }
    }

    public function publish(int $id): bool
    {
        $token = (string) Str::uuid();
        $outbox = $this->database->transaction(function () use ($id, $token): ?OrderPaidOutbox {
            $row = OrderPaidOutbox::query()->whereKey($id)->whereNull('dispatched_at')->lockForUpdate()->first();
            if ($row === null || ($row->claimed_at !== null && $row->claimed_at->gt(now()->subMinutes(5)))) {
                return null;
            }
            $row->update(['claim_token' => $token, 'claimed_at' => now()]);

            return $row->fresh();
        });
        if ($outbox === null) {
            return false;
        }

        try {
            $payload = $this->validatedPayload($outbox->payload);
            $this->events->dispatch(new OrderPaidEvent($payload['orderId'], $payload['tenantId'], $payload['userId'], $payload['paidAt'], $payload['items']));
        } catch (\Throwable $exception) {
            OrderPaidOutbox::query()->whereKey($id)->where('claim_token', $token)->update([
                'claim_token' => null,
                'claimed_at' => null,
                'attempt_count' => $outbox->attempt_count + 1,
                'last_failed_at' => now(),
                'last_error_class' => $exception::class,
            ]);
            throw $exception;
        }

        return OrderPaidOutbox::query()->whereKey($id)->where('claim_token', $token)->update(['claim_token' => null, 'dispatched_at' => now()]) === 1;
    }

    /** @return array{published: int, failed: int} */
    public function drain(int $limit = 100): array
    {
        $result = ['published' => 0, 'failed' => 0];
        foreach (OrderPaidOutbox::query()->whereNull('dispatched_at')->orderBy('id')->limit($limit)->get() as $row) {
            try {
                $result['published'] += $this->publish($row->id) ? 1 : 0;
            } catch (\Throwable) {
                $result['failed']++;
                $outbox = OrderPaidOutbox::query()->find($row->id);
                Log::warning('OrderPaid outbox publish failed.', ['outbox_id' => $row->id, 'order_id' => $row->order_id, 'exception_class' => $outbox?->last_error_class, 'attempt_count' => $outbox?->attempt_count]);
            }
        }

        return $result;
    }

    /** @return array{orderId: int, tenantId: int, userId: int, paidAt: string, items: array<int, array<string, mixed>>} */
    private function payload(OrderPaidEvent $event): array
    {
        return ['orderId' => $event->orderId, 'tenantId' => $event->tenantId, 'userId' => $event->userId, 'paidAt' => $event->paidAt, 'items' => $event->items];
    }

    /** @param array<string, mixed>|null $payload @return array{orderId: int, tenantId: int, userId: int, paidAt: string, items: array<int, array<string, mixed>>} */
    private function validatedPayload(?array $payload): array
    {
        if (! is_array($payload) || ! isset($payload['orderId'], $payload['tenantId'], $payload['userId'], $payload['paidAt'], $payload['items']) || ! is_array($payload['items'])) {
            throw new \UnexpectedValueException('Invalid OrderPaid outbox payload.');
        }

        return $payload;
    }
}
