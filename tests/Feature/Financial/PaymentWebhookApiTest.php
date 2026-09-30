<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayWebhookInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use App\Modules\Financial\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\OrderPaidOutbox;
use App\Modules\Financial\Models\Payment;
use App\Modules\Financial\Services\Outbox\OrderPaidOutboxService;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class WebhookCapableFakeGateway implements PaymentGatewayInterface, PaymentGatewayWebhookInterface
{
    public function identifier(): string
    {
        return 'webhook-fake';
    }

    public function label(): string
    {
        return 'Webhook fake';
    }

    public function confirmationMode(): PaymentConfirmationMode
    {
        return PaymentConfirmationMode::Automatic;
    }

    public function configurationSchema(): GatewayConfigurationDefinition
    {
        return new GatewayConfigurationDefinition(
            identifier: $this->identifier(),
            label: $this->label(),
            fields: [
                'webhook_secret' => [
                    'label' => 'Segredo do webhook',
                    'input' => 'password',
                    'required' => true,
                    'secret' => true,
                    'rules' => ['string', 'min:8'],
                ],
            ],
        );
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        return new ChargeResult(status: \App\Modules\Financial\Enums\PaymentChargeStatus::Pending);
    }

    public function validateConfiguration(array $config): bool
    {
        return isset($config['webhook_secret']) && is_string($config['webhook_secret']);
    }

    public function verifyWebhookSignature(array $credentials, string $payload, string $signature): bool
    {
        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        return is_string($provided)
            && hash_equals(hash_hmac('sha256', $payload, $credentials['webhook_secret']), strtolower($provided));
    }
}

class WebhookHmacFallbackFakeGateway implements PaymentGatewayInterface
{
    public function identifier(): string
    {
        return 'webhook-hmac-fake';
    }

    public function label(): string
    {
        return 'Webhook HMAC fake';
    }

    public function confirmationMode(): PaymentConfirmationMode
    {
        return PaymentConfirmationMode::Automatic;
    }

    public function configurationSchema(): GatewayConfigurationDefinition
    {
        return new GatewayConfigurationDefinition(
            identifier: $this->identifier(),
            label: $this->label(),
            fields: [
                'webhook_secret' => [
                    'label' => 'Segredo do webhook',
                    'input' => 'password',
                    'required' => true,
                    'secret' => true,
                    'rules' => ['string', 'min:8'],
                ],
            ],
        );
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        return new ChargeResult(status: \App\Modules\Financial\Enums\PaymentChargeStatus::Pending);
    }

    public function validateConfiguration(array $config): bool
    {
        return isset($config['webhook_secret']) && is_string($config['webhook_secret']);
    }
}

class ThrowingWebhookGateway extends WebhookCapableFakeGateway
{
    public function identifier(): string
    {
        return 'webhook-throwing';
    }

    public function verifyWebhookSignature(array $credentials, string $payload, string $signature): bool
    {
        throw new RuntimeException('Webhook provider unavailable.');
    }
}

/** @return array{tenant: Tenant, order: Order, payment: Payment, config: TenantPluginConfig, secret: string} */
function webhookPaymentFixture(string $status = 'pending', ?PaymentGatewayInterface $gateway = null): array
{
    $tenant = makeTenant();
    $user = User::factory()->student()->forTenant($tenant)->create();
    $gateway ??= new WebhookCapableFakeGateway;
    app(PaymentGatewayManager::class)->register($gateway);

    $plugin = Plugin::factory()->published()->gateway($gateway->identifier())->create();
    PluginActivation::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'status' => 'active',
    ]);
    $secret = 'webhook-test-secret';
    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'enabled' => true,
        'config' => ['webhook_secret' => $secret],
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => $status,
        'order_number' => 'ORD-WEBHOOK-123',
    ]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => $status === 'paid' ? 'completed' : 'pending',
        'gateway_slug' => $gateway->identifier(),
        'confirmation_mode' => 'automatic',
        'external_id' => 'pay_webhook_123',
        'tenant_plugin_config_id' => $config->id,
        'gateway_configuration_version' => $config->configuration_version,
        'charge_state' => $status === 'paid' ? 'resolved' : 'created',
    ]);

    return compact('tenant', 'order', 'payment', 'config', 'secret');
}

/** @param array<string, string> $payload */
function signedWebhookRequest(array $payload, string $secret, string $gatewaySlug = 'webhook-fake'): \Illuminate\Testing\TestResponse
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call('POST', "/api/v1/webhooks/gateways/{$gatewaySlug}", [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, $secret),
    ], $raw);
}

it('accepts a signed webhook without authentication and queues processing', function (): void {
    Queue::fake();
    $fixture = webhookPaymentFixture();

    $response = signedWebhookRequest([
        'status' => 'paid',
        'order_number' => $fixture['order']->order_number,
        'external_id' => $fixture['payment']->external_id,
    ], $fixture['secret']);

    $response->assertAccepted()->assertJsonPath('data.accepted', true);
    expect($fixture['order']->fresh()->status)->toBe('pending');
    Queue::assertPushed(ProcessPaymentWebhookJob::class, function (ProcessPaymentWebhookJob $job) use ($fixture): bool {
        return $job->paymentId === $fixture['payment']->id
            && $job->gatewaySlug === 'webhook-fake'
            && $job->status === 'paid';
    });
});

it('rejects an invalid webhook signature before queueing', function (): void {
    Queue::fake();
    $fixture = webhookPaymentFixture();
    $raw = json_encode([
        'status' => 'paid',
        'order_number' => $fixture['order']->order_number,
        'external_id' => $fixture['payment']->external_id,
    ], JSON_THROW_ON_ERROR);

    $response = $this->call('POST', '/api/v1/webhooks/gateways/webhook-fake', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash('sha256', 'wrong'),
    ], $raw);

    assertApiErrorEnvelope($response, 422, 'validation_error');
    Queue::assertNothingPushed();
});

it('shares the webhook rate limit across gateway slugs for the same client IP', function (): void {
    $limiter = app(RateLimiter::class)->limiter('payment-webhook');
    $firstRequest = Request::create('/api/v1/webhooks/gateways/stripe', 'POST', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.44',
    ]);
    $secondRequest = Request::create('/api/v1/webhooks/gateways/mercadopago', 'POST', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.44',
    ]);

    expect($limiter)->toBeInstanceOf(\Closure::class);
    assert($limiter instanceof \Closure);

    $firstLimit = $limiter($firstRequest);
    $secondLimit = $limiter($secondRequest);

    expect($firstLimit->key)->toBe($secondLimit->key)
        ->and($firstLimit->maxAttempts)->toBe(120);
});

it('returns gateway unavailable when historical gateway configuration cannot be resolved', function (): void {
    Queue::fake();
    $fixture = webhookPaymentFixture();
    $fixture['config']->revisions()
        ->where('configuration_version', $fixture['payment']->gateway_configuration_version)
        ->delete();

    $response = signedWebhookRequest([
        'status' => 'paid',
        'order_number' => $fixture['order']->order_number,
        'external_id' => $fixture['payment']->external_id,
    ], $fixture['secret']);

    assertApiErrorEnvelope($response, 503, 'gateway_unavailable');
    Queue::assertNothingPushed();
});

it('returns gateway unavailable when the webhook adapter cannot verify a signature', function (): void {
    Queue::fake();
    $fixture = webhookPaymentFixture(gateway: new ThrowingWebhookGateway);

    $response = signedWebhookRequest([
        'status' => 'paid',
        'order_number' => $fixture['order']->order_number,
        'external_id' => $fixture['payment']->external_id,
    ], $fixture['secret'], 'webhook-throwing');

    assertApiErrorEnvelope($response, 503, 'gateway_unavailable');
    Queue::assertNothingPushed();
});

it('returns gateway unavailable when webhook job dispatch fails', function (): void {
    $fixture = webhookPaymentFixture();
    $failingDispatcher = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
    $failingDispatcher->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(ProcessPaymentWebhookJob::class))
        ->andThrow(new RuntimeException('Queue secret must not escape.'));
    app()->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $failingDispatcher);

    $response = signedWebhookRequest([
        'status' => 'paid',
        'order_number' => $fixture['order']->order_number,
        'external_id' => $fixture['payment']->external_id,
    ], $fixture['secret']);

    assertApiErrorEnvelope($response, 503, 'gateway_unavailable');
    expect($response->getContent())->not->toContain('Queue secret');
});

it('verifies the generic HMAC fallback when the adapter has no webhook interface', function (): void {
    Queue::fake();
    $fixture = webhookPaymentFixture(gateway: new WebhookHmacFallbackFakeGateway);

    $response = signedWebhookRequest([
        'status' => 'paid',
        'order_number' => $fixture['order']->order_number,
        'external_id' => $fixture['payment']->external_id,
    ], $fixture['secret'], 'webhook-hmac-fake');

    $response->assertAccepted()->assertJsonPath('data.accepted', true);
    Queue::assertPushed(ProcessPaymentWebhookJob::class);
});

it('moves a pending payment to paid and records one durable paid outbox event', function (): void {
    Event::fake();
    $fixture = webhookPaymentFixture();
    $job = new ProcessPaymentWebhookJob(
        paymentId: $fixture['payment']->id,
        gatewaySlug: 'webhook-fake',
        status: 'paid',
        externalId: $fixture['payment']->external_id,
        orderNumber: $fixture['order']->order_number,
    );

    $job->handle(app(DatabaseManager::class), app(OrderPaidOutboxService::class));
    $job->handle(app(DatabaseManager::class), app(OrderPaidOutboxService::class));

    expect($fixture['order']->fresh()->status)->toBe('paid')
        ->and($fixture['payment']->fresh()->status)->toBe('completed')
        ->and($fixture['payment']->fresh()->charge_state)->toBe('resolved')
        ->and(OrderPaidOutbox::query()->where('order_id', $fixture['order']->id)->count())->toBe(1);
});

it('publishes the paid event and enrolls the course from a webhook payment', function (): void {
    $fixture = webhookPaymentFixture();
    $course = Course::factory()->create(['tenant_id' => $fixture['tenant']->id]);
    OrderItem::factory()->create([
        'order_id' => $fixture['order']->id,
        'itemable_type' => Course::class,
        'itemable_id' => $course->id,
        'item_snapshot' => ['title' => $course->title],
        'price_cents' => 12900,
    ]);
    $job = new ProcessPaymentWebhookJob(
        paymentId: $fixture['payment']->id,
        gatewaySlug: 'webhook-fake',
        status: 'paid',
        externalId: $fixture['payment']->external_id,
        orderNumber: $fixture['order']->order_number,
    );

    $job->handle(app(DatabaseManager::class), app(OrderPaidOutboxService::class));

    expect(Enrollment::query()
        ->where('tenant_id', $fixture['tenant']->id)
        ->where('user_id', $fixture['order']->user_id)
        ->where('course_id', $course->id)
        ->count())->toBe(1)
        ->and(OrderPaidOutbox::query()->where('order_id', $fixture['order']->id)->firstOrFail()->dispatched_at)->not->toBeNull();
});

it('moves a pending payment to failed without emitting a paid outbox event', function (): void {
    Event::fake();
    $fixture = webhookPaymentFixture();
    $job = new ProcessPaymentWebhookJob(
        paymentId: $fixture['payment']->id,
        gatewaySlug: 'webhook-fake',
        status: 'failed',
        externalId: $fixture['payment']->external_id,
        orderNumber: $fixture['order']->order_number,
    );

    $job->handle(app(DatabaseManager::class), app(OrderPaidOutboxService::class));

    expect($fixture['order']->fresh()->status)->toBe('failed')
        ->and($fixture['payment']->fresh()->status)->toBe('failed')
        ->and($fixture['payment']->fresh()->charge_state)->toBe('resolved')
        ->and(OrderPaidOutbox::query()->count())->toBe(0);
});
