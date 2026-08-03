<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\TenantCustomization;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Actions\Enrollment\EnrollmentFinancialMirrorIntegrityException;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;
use App\Modules\Learning\Enums\EnrollmentBillingType;
use App\Modules\Learning\Events\EnrollmentCreatedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use Ramsey\Uuid\Uuid;

it('creates an exact zero-consideration financial mirror for a free manual enrollment', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = freeCourse($tenant);
    $student = User::factory()->forTenant($tenant)->student()->create();

    $this->postJson('/api/v1/learning/enrollments', ['course_id' => $course->id, 'user_id' => $student->id], $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');

    $enrollment = Enrollment::query()->sole();
    $order = Order::query()->with(['items', 'payments'])->sole();
    $item = $order->items->sole();
    $payment = $order->payments->sole();

    expect($order->only(['tenant_id', 'user_id', 'order_number', 'status', 'origin_type', 'subtotal_cents', 'tax_cents', 'total_cents', 'source_key']))
        ->toBe([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'order_number' => 'ENR-'.$enrollment->id,
            'status' => 'paid',
            'origin_type' => 'direct',
            'subtotal_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 0,
            'source_key' => 'learning:enrollment:'.$enrollment->id,
        ])
        ->and($order->metadata['enrollment_status'])->toBe('active')
        ->and($order->metadata['source'])->toBe('manual')
        ->and($order->metadata['billing_type'])->toBeNull()
        ->and($order->metadata['created_by_instructor_id'])->toBeNull()
        ->and($order->metadata['occurred_at'])->toBeString()
        ->and($order->idempotency_key)->toBe(Uuid::uuid5(Uuid::NAMESPACE_URL, 'learning:enrollment:'.$enrollment->id)->toString())
        ->and($item->itemable_type)->toBe('course')
        ->and($item->itemable_id)->toBe($course->id)
        ->and($item->item_snapshot['title'])->toBe($course->title)
        ->and($item->item_snapshot['slug'])->toBe($course->slug)
        ->and($item->item_snapshot['catalog_price_cents'])->toBe(0)
        ->and($item->item_snapshot['enrollment_id'])->toBe($enrollment->id)
        ->and($item->price_cents)->toBe(0)
        ->and($payment->only(['status', 'gateway_slug', 'confirmation_mode', 'charge_state', 'external_id', 'gateway_response']))->toBe([
            'status' => 'completed',
            'gateway_slug' => 'free',
            'confirmation_mode' => 'automatic',
            'charge_state' => 'resolved',
            'external_id' => null,
            'gateway_response' => null,
        ]);
});

it('mirrors approval-required free manual enrollments as paid zero-consideration orders', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    TenantCustomization::query()->create(['tenant_id' => $tenant->id, 'published_settings' => ['learning' => ['enrollments' => ['manual_free_by_instructor' => true, 'manual_free_requires_approval' => true]]]]);
    $course = freeCourse($tenant);
    $student = User::factory()->forTenant($tenant)->student()->create();

    $this->postJson('/api/v1/learning/enrollments', ['course_id' => $course->id, 'user_id' => $student->id], $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    expect(Order::query()->sole()->status)->toBe('paid')
        ->and(Order::query()->sole()->total_cents)->toBe(0)
        ->and(Order::query()->sole()->metadata['enrollment_status'])->toBe('pending');
});

it('mirrors an admin manual grant into a paid catalog course at zero consideration', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = freeCourse($tenant, 12900);
    $student = User::factory()->forTenant($tenant)->student()->create();

    $this->postJson('/api/v1/learning/enrollments', ['course_id' => $course->id, 'user_id' => $student->id], $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');

    $order = Order::query()->with('items')->sole();

    expect($order->total_cents)->toBe(0)
        ->and($order->items->sole()->price_cents)->toBe(0)
        ->and($order->items->sole()->item_snapshot['catalog_price_cents'])->toBe(12900);
});

it('keeps one order item and payment when a free enrollment event is replayed', function (): void {
    $tenant = makeTenant();
    $student = User::factory()->forTenant($tenant)->student()->create();
    $event = enrollmentCreatedEvent($tenant->id, $student->id, 42);

    event($event);
    event($event);

    expect(Order::query()->count())->toBe(1)
        ->and(OrderItem::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1);
});

it('rolls back enrollment when the financial mirror listener fails', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = freeCourse($tenant);
    $student = User::factory()->forTenant($tenant)->student()->create();

    Payment::creating(function (): void {
        throw new \RuntimeException('mirror failed');
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/api/v1/learning/enrollments', ['course_id' => $course->id, 'user_id' => $student->id], $headers))
        ->toThrow(\RuntimeException::class, 'mirror failed');

    expect(Enrollment::query()->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0);
});

it('rejects a conflicting deterministic enrollment order identity without creating a partial mirror', function (): void {
    $tenant = makeTenant();
    $student = User::factory()->forTenant($tenant)->student()->create();
    $event = enrollmentCreatedEvent($tenant->id, $student->id, 42);
    $sourceKey = 'learning:enrollment:42';
    $idempotencyKey = Uuid::uuid5(Uuid::NAMESPACE_URL, $sourceKey)->toString();

    Order::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'order_number' => 'ENR-42',
        'status' => 'paid',
        'origin_type' => 'direct',
        'subtotal_cents' => 0,
        'tax_cents' => 0,
        'total_cents' => 0,
        'source_key' => 'wrong-source',
        'idempotency_key' => $idempotencyKey,
        'metadata' => ['source' => 'manual', 'billing_type' => null],
    ]);

    expect(fn () => event($event))->toThrow(EnrollmentFinancialMirrorIntegrityException::class)
        ->and(Order::query()->count())->toBe(1)
        ->and(OrderItem::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

it('keeps checkout ledger intact when its order paid event creates enrollment', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = freeCourse($tenant);

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], array_merge($headers, [
        'Idempotency-Key' => '3b4e1dc1-0ef6-46d8-9bea-aa992d719744',
    ]))->assertCreated()->assertJsonPath('data.status', 'paid');

    expect(Enrollment::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(1)
        ->and(OrderItem::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1);
});

it('does not create a free mirror for externally billed manual enrollment', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $course = freeCourse($tenant, 1000);
    $student = User::factory()->forTenant($tenant)->student()->create();

    $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
        'user_id' => $student->id,
        'billing_type' => EnrollmentBillingType::External->value,
    ], $headers)->assertCreated()->assertJsonPath('data.status', 'pending');

    expect(Enrollment::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(0);
});

function freeCourse(object $tenant, int $priceCents = 0): Course
{
    return Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Free Course',
        'slug' => 'free-course-'.fake()->unique()->numberBetween(1, 999999),
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => $priceCents,
        'access_days' => 30,
        'is_featured' => false,
    ]);
}

function enrollmentCreatedEvent(int $tenantId, int $userId, int $enrollmentId): EnrollmentCreatedEvent
{
    return new EnrollmentCreatedEvent(
        enrollmentId: $enrollmentId,
        tenantId: $tenantId,
        userId: $userId,
        courseId: 7,
        courseTitle: 'Free Course',
        courseSlug: 'free-course',
        coursePriceCents: 0,
        status: 'active',
        source: 'manual',
        billingType: null,
        createdByInstructorId: null,
        occurredAt: '2026-07-29T00:00:00+00:00',
    );
}
