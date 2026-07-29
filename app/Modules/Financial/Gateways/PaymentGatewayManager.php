<?php

namespace App\Modules\Financial\Gateways;

use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Contracts\GatewayConfigurationRegistry;
use App\Modules\Financial\Contracts\GatewayConfigurationValidationResult;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use Illuminate\Contracts\Validation\Factory;

/**
 * Registro de adaptadores de gateway de pagamento.
 *
 * Os adaptadores concretos (ex.: StripeGateway) registram-se aqui no boot; a
 * fundação não trava em nenhum PSP (ADR-001) e serve os dois ledgers via
 * contrato agnóstico (ADR-003).
 *
 * A resolução do gateway ativo por tenant vive no Ecosystem, que combina
 * entitlement + config cifrada e os entrega atomicamente ao
 * `TenantGatewayResolver`. Este manager conhece somente adaptadores stateless.
 */
class PaymentGatewayManager implements GatewayConfigurationRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function __construct(private readonly Factory $validator) {}

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->identifier()] = $gateway;
    }

    public function get(string $identifier): ?PaymentGatewayInterface
    {
        return $this->gateways[$identifier] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->gateways[$identifier]);
    }

    public function definition(string $identifier): ?GatewayConfigurationDefinition
    {
        return $this->get($identifier)?->configurationSchema();
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(string $identifier, array $configuration): GatewayConfigurationValidationResult
    {
        $gateway = $this->get($identifier);

        if ($gateway === null) {
            return new GatewayConfigurationValidationResult(false, [
                'gateway' => ['Gateway indisponível.'],
            ]);
        }

        $schema = $gateway->configurationSchema();
        $errors = [];

        foreach (array_keys($configuration) as $key) {
            if (! array_key_exists($key, $schema->fields)) {
                $errors[$key] = ['Campo de configuração desconhecido.'];
            }
        }

        if ($errors !== []) {
            return new GatewayConfigurationValidationResult(false, $errors);
        }

        $rules = [];

        foreach ($schema->fields as $key => $field) {
            $rules[$key] = $field['required']
                ? ['required', ...$field['rules']]
                : $field['rules'];
        }

        $validator = $this->validator->make($configuration, $rules);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $errors */
            $errors = $validator->errors()->toArray();

            return new GatewayConfigurationValidationResult(false, $errors);
        }

        if (! $gateway->validateConfiguration($configuration)) {
            return new GatewayConfigurationValidationResult(false, [
                'configuration' => ['Configuração de gateway inválida.'],
            ]);
        }

        return new GatewayConfigurationValidationResult(true);
    }

    /** @return array<string, PaymentGatewayInterface> */
    public function all(): array
    {
        return $this->gateways;
    }
}
