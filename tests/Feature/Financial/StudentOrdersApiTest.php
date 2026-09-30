<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;

function studentOrder(Tenant $tenant, User $student, array $attributes = []): Order
{
    $order = Order::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'status' => 'paid',
        'origin_type' => 'direct',
        'subtotal_cents' => 12900,
        'tax_cents' => 0,
        'total_cents' => 12900,
    ], $attributes));

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'itemable_type' => 'course',
        'itemable_id' => 999,
        'item_snapshot' => ['title' => 'Curso vendido'],
        'price_cents' => $order->total_cents,
    ]);

    Payment::factory()->create([
        'order_id' => $order->id,
        'status' => $order->status === 'paid' ? 'completed' : 'pending',
        'gateway_slug' => 'cash',
        'confirmation_mode' => 'manual',
        'charge_state' => $order->status === 'paid' ? 'resolved' : 'created',
    ]);

    return $order;
}

it('lists only the student orders with cursor pagination and safe fields', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $otherStudent = User::factory()->student()->forTenant($tenant)->create();
    $orders = collect(range(1, 16))->map(
        fn (int $number): Order => studentOrder($tenant, $student, ['order_number' => "ORD-STUDENT-{$number}"])
    );
    studentOrder($tenant, $otherStudent, ['order_number' => 'ORD-OTHER-USER']);

    $response = $this->getJson('/api/v1/student/orders', $headers);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [[
                'id', 'order_number', 'status', 'origin_type', 'subtotal_cents',
                'tax_cents', 'total_cents', 'items', 'payments', 'created_at', 'updated_at',
            ]],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta',
        ])
        ->assertJsonCount(15, 'data')
        ->assertJsonMissing(['source_key', 'idempotency_key', 'gateway_response', 'metadata', 'client_secret']);

    expect($response->json('links.next'))->toBeString();

    parse_str((string) parse_url($response->json('links.next'), PHP_URL_QUERY), $query);
    $nextPage = $this->getJson(
        '/api/v1/student/orders?cursor='.urlencode((string) ($query['cursor'] ?? '')),
        $headers,
    );

    $nextPage->assertSuccessful()->assertJsonCount(1, 'data');
    expect($nextPage->json('data.0.order_number'))->toBe($orders->first()->order_number);
});

it('shows a student order from the same tenant and user only', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $order = studentOrder($tenant, $student, ['order_number' => 'ORD-SHOW']);

    $this->getJson("/api/v1/student/orders/{$order->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.order_number', 'ORD-SHOW')
        ->assertJsonPath('data.payments.0.status', 'completed')
        ->assertJsonMissing(['source_key', 'idempotency_key', 'gateway_response', 'metadata', 'client_secret']);
});

it('hides another student order and another tenant order as not found', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $otherStudent = User::factory()->student()->forTenant($tenant)->create();
    $otherUserOrder = studentOrder($tenant, $otherStudent);
    $otherTenant = makeTenant();
    $otherTenantStudent = User::factory()->student()->forTenant($otherTenant)->create();
    $otherTenantOrder = studentOrder($otherTenant, $otherTenantStudent);

    assertApiErrorEnvelope(
        $this->getJson("/api/v1/student/orders/{$otherUserOrder->id}", $headers),
        404,
        'not_found',
    );
    assertTenantIsolation($this->getJson("/api/v1/student/orders/{$otherTenantOrder->id}", $headers));
});

it('requires a student token and the orders permission', function (): void {
    $tenant = makeTenant();
    assertApiErrorEnvelope($this->getJson('/api/v1/student/orders', tenantHeaders($tenant)), 401, 'unauthenticated');

    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $student->removeRole('student');

    assertApiErrorEnvelope($this->getJson('/api/v1/student/orders', $headers), 403, 'access_denied');
});

it('validates the opaque cursor and rejects client-controlled listing options', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/student/orders?cursor='.str_repeat('a', 513), $headers),
        422,
        'validation_error',
    );

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/student/orders?cursor=e30', $headers),
        422,
        'validation_error',
    );

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/student/orders?per_page=100', $headers),
        422,
        'validation_error',
    );
});

it('rejects non-student personas before reaching the orders action', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    assertApiErrorEnvelope($this->getJson('/api/v1/student/orders', $headers), 403, 'area_forbidden');
});
