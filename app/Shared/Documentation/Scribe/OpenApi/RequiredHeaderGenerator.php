<?php

namespace App\Shared\Documentation\Scribe\OpenApi;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

class RequiredHeaderGenerator extends OpenApiGenerator
{
    /**
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @param  array<string, mixed>  $pathItem
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        if (! in_array('POST', $endpoint->httpMethods, true) || ! isset($pathItem['parameters'])) {
            return $pathItem;
        }

        if (! in_array(trim($endpoint->uri, '/'), [
            'api/v1/student/checkout',
            'api/v1/webhooks/gateways/{gateway_slug}',
        ], true)) {
            return $pathItem;
        }

        $requiredHeaders = [
            'api/v1/student/checkout' => 'Idempotency-Key',
            'api/v1/webhooks/gateways/{gateway_slug}' => 'X-Webhook-Signature',
        ];
        $requiredHeader = $requiredHeaders[trim($endpoint->uri, '/')];

        $pathItem['parameters'] = array_map(
            static function (array $parameter) use ($requiredHeader): array {
                if (($parameter['name'] ?? null) === $requiredHeader && ($parameter['in'] ?? null) === 'header') {
                    $parameter['required'] = true;
                }

                return $parameter;
            },
            $pathItem['parameters'],
        );

        return $pathItem;
    }
}
