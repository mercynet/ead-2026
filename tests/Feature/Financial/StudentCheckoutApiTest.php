<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Actions\Student\StoreCheckoutAction;
use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use App\Modules\Financial\Gateways\TenantGatewayResolver;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\OrderPaidOutbox;
use App\Modules\Financial\Models\Payment;
use App\Modules\Financial\Services\Outbox\OrderPaidOutboxService;
use App\Modules\Learning\Contracts\CourseCheckoutCatalog;
use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

class CheckoutAutomaticGateway implements PaymentGatewayInterface
{
    public int $charges = 0;

    /** @var array<int, array<string, mixed>> */
    public array $credentialsSeen = [];

    /** @var array<int, ChargeIntent> */
    public array $intents = [];

    public ?\Closure $duringCharge = null;

    public function __construct(
        public PaymentChargeStatus $result = PaymentChargeStatus::Pending,
        public bool $throws = false,
        private readonly string $id = 'checkout-fake',
        private readonly array $raw = ['psp_secret' => 'hidden'],
    ) {}

    public function identifier(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return 'Checkout fake';
    }

    public function confirmationMode(): PaymentConfirmationMode
    {
        return PaymentConfirmationMode::Automatic;
    }

    public function configurationSchema(): GatewayConfigurationDefinition
    {
        return new GatewayConfigurationDefinition($this->identifier(), $this->label(), []);
    }

    public function validateConfiguration(array $config): bool
    {
        return true;
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        $this->charges++;
        $this->credentialsSeen[] = $credentials;
        $this->intents[] = $intent;
        ($this->duringCharge)?->__invoke();
        if ($this->throws) {
            throw new RuntimeException('PSP secret '.$credentials['secret']);
        }

        return new ChargeResult($this->result, 'psp_'.$intent->reference, 'https://psp.test/pay', 'client_secret', $this->raw);
    }
}

function checkoutCourse(Tenant $tenant, array $attributes = []): Course
{
    return Course::query()->create(array_merge([
        'tenant_id' => $tenant->id, 'title' => 'Checkout course', 'slug' => 'checkout-'.fake()->unique()->numberBetween(1, 999999),
        'description' => 'Description', 'status' => 'published', 'is_active' => true, 'price_cents' => 12900,
        'access_days' => 30, 'is_featured' => false,
    ], $attributes));
}

function checkoutGateway(Tenant $tenant, CheckoutAutomaticGateway $gateway, array $config = ['secret' => 'tenant-secret']): void
{
    app(PaymentGatewayManager::class)->register($gateway);
    $plugin = Plugin::factory()->published()->gateway($gateway->identifier())->create();
    PluginActivation::factory()->create(['tenant_id' => $tenant->id, 'plugin_id' => $plugin->id, 'status' => 'active']);
    TenantPluginConfig::factory()->create(['tenant_id' => $tenant->id, 'plugin_id' => $plugin->id, 'enabled' => true, 'config' => $config]);
}

function checkoutCashGateway(Tenant $tenant): void
{
    $plugin = Plugin::factory()->published()->gateway('cash')->create();
    PluginActivation::factory()->create(['tenant_id' => $tenant->id, 'plugin_id' => $plugin->id, 'status' => 'active']);
    TenantPluginConfig::factory()->create(['tenant_id' => $tenant->id, 'plugin_id' => $plugin->id, 'enabled' => true, 'config' => []]);
}

function checkoutRequestHeaders(array $headers, string $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744'): array
{
    return array_merge($headers, ['Idempotency-Key' => $key]);
}

it('creates pending cash checkout with server price and safe response', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = checkoutCourse($tenant, ['price_cents' => 12900]);
    checkoutCashGateway($tenant);

    $response = $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id, 'price_cents' => 1], checkoutRequestHeaders($headers));
    $response->assertUnprocessable();

    $response = $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers));
    $response->assertCreated()->assertJsonPath('data.total_cents', 12900)->assertJsonPath('data.origin_type', 'direct')
        ->assertJsonPath('data.payment.gateway_slug', 'cash')->assertJsonPath('data.payment.confirmation_mode', 'manual')
        ->assertJsonMissing(['source_key', 'idempotency_key', 'gateway_response', 'metadata']);
    expect(Order::query()->count())->toBe(1)->and(Payment::query()->firstOrFail()->status)->toBe('pending');
});

it('handles free, automatic pending paid and failed checkout results', function (int $price, PaymentChargeStatus $result, string $orderStatus): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = checkoutCourse($tenant, ['price_cents' => $price]);
    if ($price > 0) {
        checkoutGateway($tenant, new CheckoutAutomaticGateway($result));
    }

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers))
        ->assertCreated()->assertJsonPath('data.status', $orderStatus);
    expect(Order::query()->firstOrFail()->status)->toBe($orderStatus);
})->with([
    'free' => [0, PaymentChargeStatus::Paid, 'paid'],
    'pending' => [12900, PaymentChargeStatus::Pending, 'pending'],
    'paid' => [12900, PaymentChargeStatus::Paid, 'paid'],
    'failed' => [12900, PaymentChargeStatus::Failed, 'failed'],
]);

it('replays exact key without another charge and rejects conflicting keys', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $gateway = new CheckoutAutomaticGateway;
    checkoutGateway($tenant, $gateway);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertCreated();
    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertOk();
    expect($gateway->charges)->toBe(1)->and(Order::query()->count())->toBe(1);

    $other = checkoutCourse($tenant);
    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $other->id], checkoutRequestHeaders($headers, $key)), 409, 'idempotency_conflict');
    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, '4b4e1dc1-0ef6-46d8-9bea-aa992d719744')), 409, 'checkout_already_exists');
});

it('replays the persisted order after a concurrent idempotency insert loses the unique race', function (): void {
    $tenant = makeTenant();
    [$student] = actingAsUserType(UserType::Student, $tenant);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';
    $offering = app(CourseCheckoutCatalog::class)->resolve($tenant->id, $student->id, $course->id);

    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'order_number' => 'ORD-CONCURRENT-REPLAY',
        'status' => 'paid',
        'origin_type' => 'direct',
        'subtotal_cents' => $course->price_cents,
        'tax_cents' => 0,
        'total_cents' => $course->price_cents,
        'source_key' => $offering->purchaseCycleKey,
        'idempotency_key' => $key,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'itemable_type' => 'course',
        'itemable_id' => $course->id,
        'item_snapshot' => $offering->snapshot,
        'price_cents' => $course->price_cents,
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'status' => 'completed',
        'gateway_slug' => 'cash',
        'confirmation_mode' => 'manual',
        'charge_state' => 'resolved',
    ]);

    $previous = new \PDOException('Duplicate entry for key orders_tenant_user_idempotency_unique', 1062);
    $previous->errorInfo = ['23000', 1062, 'Duplicate entry for key orders_tenant_user_idempotency_unique'];
    $database = Mockery::mock(DatabaseManager::class);
    $attempt = 0;
    $database->shouldReceive('transaction')->twice()->ordered()->andReturnUsing(function (callable $callback) use (&$attempt, $previous): mixed {
        $attempt++;
        if ($attempt === 1) {
            throw new QueryException('mysql', 'insert into orders', [], $previous);
        }

        return $callback();
    });

    $action = new StoreCheckoutAction(
        $database,
        app(CourseCheckoutCatalog::class),
        app(TenantGatewayResolver::class),
        app(OrderPaidOutboxService::class),
    );

    $replayed = $action->handle(new ApiContext($student, $tenant), $course->id, $key);

    expect($replayed->id)->toBe($order->id)
        ->and($replayed->wasRecentlyCreated)->toBeFalse()
        ->and(Order::query()->count())->toBe(1)
        ->and($attempt)->toBe(2);
});

it('replays a resolved payment without resolving a currently active gateway', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $gateway = new CheckoutAutomaticGateway(PaymentChargeStatus::Paid);
    checkoutGateway($tenant, $gateway);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertCreated();
    TenantPluginConfig::query()->where('tenant_id', $tenant->id)->update(['enabled' => false]);

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertOk();
    expect($gateway->charges)->toBe(1);
});

it('resolves an unresolved replay through its persisted gateway instead of a replacement gateway', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $gatewayA = new CheckoutAutomaticGateway(PaymentChargeStatus::Pending, false, 'gateway-a');
    checkoutGateway($tenant, $gatewayA);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertCreated();
    $payment = Payment::query()->firstOrFail();
    $persistedIdentity = [
        'gateway_slug' => $payment->gateway_slug,
        'tenant_plugin_config_id' => $payment->tenant_plugin_config_id,
        'gateway_configuration_version' => $payment->gateway_configuration_version,
        'psp_idempotency_key' => $payment->psp_idempotency_key,
    ];
    $payment->update(['charge_state' => 'created', 'gateway_response' => null, 'external_id' => null]);

    $gatewayB = new CheckoutAutomaticGateway(PaymentChargeStatus::Pending, false, 'gateway-b');
    checkoutGateway($tenant, $gatewayB);
    TenantPluginConfig::query()->whereKey($payment->tenant_plugin_config_id)->update(['config' => ['secret' => 'rotated-secret'], 'enabled' => false]);

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertOk();
    expect($gatewayA->charges)->toBe(2)
        ->and($gatewayB->charges)->toBe(0)
        ->and($gatewayA->credentialsSeen[1]['secret'])->toBe('tenant-secret')
        ->and($gatewayA->intents[1]->idempotencyKey)->toBe($persistedIdentity['psp_idempotency_key'])
        ->and($payment->fresh()->only(array_keys($persistedIdentity)))->toBe($persistedIdentity);
});

it('rejects a second claimant while first claimant owns processing state', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $gateway = new CheckoutAutomaticGateway(PaymentChargeStatus::Paid);
    checkoutGateway($tenant, $gateway);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';
    $secondResponse = null;

    $gateway->duringCharge = function () use (&$secondResponse, $headers, $course, $key): void {
        $secondResponse = $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key));
    };

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertCreated();

    assertApiErrorEnvelope($secondResponse, 409, 'checkout_in_progress');
    expect($gateway->charges)->toBe(1)
        ->and(Payment::query()->firstOrFail()->charge_state)->toBe('resolved')
        ->and(Order::query()->firstOrFail()->status)->toBe('paid')
        ->and(OrderPaidOutbox::query()->count())->toBe(1);
});

it('rejects late result persistence after claim ownership is replaced', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $gateway = new CheckoutAutomaticGateway(PaymentChargeStatus::Paid);
    checkoutGateway($tenant, $gateway);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    $gateway->duringCharge = function (): void {
        Payment::query()->firstOrFail()->update(['charge_claim_token' => (string) str()->uuid()]);
    };

    $response = $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key));

    assertApiErrorEnvelope($response, 409, 'payment_reconciliation_required');
    expect($gateway->charges)->toBe(1)
        ->and(Payment::query()->firstOrFail()->gateway_response)->toBeNull()
        ->and(Payment::query()->firstOrFail()->external_id)->toBeNull()
        ->and(Order::query()->firstOrFail()->status)->toBe('pending')
        ->and(OrderPaidOutbox::query()->count())->toBe(0);
    $response->assertDontSee('tenant-secret')->assertDontSee('psp_secret')->assertDontSee('client_secret');
});

it('marks adapter exceptions unknown and never retries their charge', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $gateway = new CheckoutAutomaticGateway(throws: true);
    checkoutGateway($tenant, $gateway);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key)), 503, 'gateway_unavailable');
    expect(Payment::query()->firstOrFail()->charge_state)->toBe('unknown');

    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key)), 409, 'payment_reconciliation_required');
    expect($gateway->charges)->toBe(1);
});

it('keeps the gateway error contract when marking an adapter failure unknown also fails', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    checkoutGateway($tenant, new CheckoutAutomaticGateway(throws: true));
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    $action = Mockery::mock(StoreCheckoutAction::class, [
        app(DatabaseManager::class),
        app(CourseCheckoutCatalog::class),
        app(TenantGatewayResolver::class),
        app(OrderPaidOutboxService::class),
    ])->makePartial();
    $action->shouldAllowMockingProtectedMethods()
        ->shouldReceive('markUnknown')
        ->once()
        ->andThrow(new RuntimeException('database unavailable'));
    $this->app->instance(StoreCheckoutAction::class, $action);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key)),
        503,
        'gateway_unavailable',
    );

    expect(Payment::query()->firstOrFail()->charge_state)->toBe('processing')
        ->and($student->exists)->toBeTrue();
});

it('does not misreport payment persistence failures as gateway outages', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $gateway = new CheckoutAutomaticGateway(PaymentChargeStatus::Paid, false, 'checkout-fake', ['invalid' => INF]);
    checkoutGateway($tenant, $gateway);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key)),
        500,
        'internal_error',
    );

    expect(Payment::query()->firstOrFail()->charge_state)->toBe('unknown');
    expect($gateway->charges)->toBe(1);
});

it('does not charge a recent processing claim and marks stale claims unknown without takeover', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $gateway = new CheckoutAutomaticGateway;
    checkoutGateway($tenant, $gateway);
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertCreated();
    $payment = Payment::query()->firstOrFail();
    $payment->update(['charge_state' => 'processing', 'charge_claim_token' => (string) str()->uuid(), 'charge_claimed_at' => now()]);

    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key)), 409, 'checkout_in_progress');
    expect($gateway->charges)->toBe(1);

    $payment->update(['charge_claimed_at' => now()->subMinutes(6)]);
    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key)), 409, 'payment_reconciliation_required');
    expect($payment->fresh()->charge_state)->toBe('unknown')->and($gateway->charges)->toBe(1);
});

it('records one paid outbox row for winning result and its replay', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    checkoutGateway($tenant, new CheckoutAutomaticGateway(PaymentChargeStatus::Paid));
    $course = checkoutCourse($tenant);
    $key = '3b4e1dc1-0ef6-46d8-9bea-aa992d719744';

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertCreated();
    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, $key))->assertOk();

    expect(OrderPaidOutbox::query()->count())->toBe(1);
});

it('logs checkout outbox publish failures without exposing exception details', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    checkoutGateway($tenant, new CheckoutAutomaticGateway(PaymentChargeStatus::Paid));
    $course = checkoutCourse($tenant);
    $failingDispatcher = Mockery::mock(Dispatcher::class);
    $failingDispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Raw checkout secret.'));
    app()->instance(OrderPaidOutboxService::class, new OrderPaidOutboxService(app(DatabaseManager::class), $failingDispatcher));
    $loggedContext = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$loggedContext): void {
        if ($event->level === 'warning' && $event->message === 'OrderPaid outbox publish failed.') {
            $loggedContext = $event->context;
        }
    });

    $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers, '4b4e1dc1-0ef6-46d8-9bea-aa992d719744'))
        ->assertCreated();

    $outbox = OrderPaidOutbox::query()->firstOrFail();

    expect($outbox->dispatched_at)->toBeNull()
        ->and($outbox->attempt_count)->toBe(1)
        ->and($outbox->last_error_class)->toBe(RuntimeException::class)
        ->and($loggedContext)->toMatchArray([
            'order_id' => $outbox->order_id,
            'outbox_id' => $outbox->id,
            'exception_class' => RuntimeException::class,
        ])
        ->and(json_encode($loggedContext))->not->toContain('secret');
});

it('returns canonical failures without ledger leaks', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = checkoutCourse($tenant);
    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers)), 503, 'gateway_unavailable');
    expect(Order::query()->count())->toBe(0);

    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], $headers), 422, 'validation_error');
    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id, 'metadata' => []], checkoutRequestHeaders($headers)), 422, 'validation_error');
    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => 999999], checkoutRequestHeaders($headers)), 404, 'not_found');
});

it('requires authenticated student checkout permission', function (): void {
    $tenant = makeTenant();
    $course = checkoutCourse($tenant);
    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders(tenantHeaders($tenant))), 401, 'unauthenticated');
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    assertApiErrorEnvelope($this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders($headers)), 403, 'area_forbidden');
});

it('forbids developer from student checkout without creating ledger records', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Developer);
    $course = checkoutCourse($tenant);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/student/checkout', ['course_id' => $course->id], checkoutRequestHeaders(array_merge($headers, ['X-Tenant-ID' => (string) $tenant->id]))),
        403,
        'area_forbidden',
    );

    expect(Order::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});
