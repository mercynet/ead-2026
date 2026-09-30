<?php

use App\Modules\Financial\Http\Requests\Student\ListStudentOrdersRequest;
use App\Modules\Financial\Http\Requests\Webhooks\StorePaymentWebhookRequest;
use App\Shared\Documentation\Scribe\BodyParameters\GetFromFormRequest as SafeBodyParametersFromFormRequest;
use App\Shared\Documentation\Scribe\QueryParameters\GetFromFormRequest as SafeQueryParametersFromFormRequest;
use Illuminate\Support\Facades\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;

uses(Tests\TestCase::class);

class ScribeDocumentationProbeController
{
    public function orders(ListStudentOrdersRequest $request): void {}

    public function webhook(StorePaymentWebhookRequest $request): void {}
}

it('does not document prohibited query or body fields', function (): void {
    $config = new DocumentationConfig(config('scribe'));
    $queryParameters = (new SafeQueryParametersFromFormRequest($config))->getParametersFromFormRequest(
        new \ReflectionMethod(ScribeDocumentationProbeController::class, 'orders'),
        Route::get('api/v1/student/orders', [ScribeDocumentationProbeController::class, 'orders']),
    );
    $bodyParameters = (new SafeBodyParametersFromFormRequest($config))->getParametersFromFormRequest(
        new \ReflectionMethod(ScribeDocumentationProbeController::class, 'webhook'),
        Route::post('api/v1/webhooks/gateways/{gateway_slug}', [ScribeDocumentationProbeController::class, 'webhook']),
    );

    expect(array_keys($queryParameters))->toBe(['cursor'])
        ->and(array_keys($bodyParameters))->toBe(['status', 'order_number', 'external_id']);
});
