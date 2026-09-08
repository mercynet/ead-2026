<?php

namespace App\Shared\Database;

use Illuminate\Support\Arr;

final class DestructiveDatabaseGuard
{
    public function denialReason(?string $requiredEnvironment = null): ?string
    {
        $environment = (string) config('app.env');
        $connectionName = (string) config('database.default');
        $connection = (array) config("database.connections.{$connectionName}", []);
        $database = (string) ($connection['database'] ?? '');
        $host = (string) ($connection['host'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $marker = (string) config('database.destructive_operations.disposable_marker', '');

        if ($this->containsProductionIndicator([$environment, $connectionName, $database, $host, $username, $marker])) {
            return 'indicador de produção encontrado na identidade do destino';
        }

        $allowedEnvironments = Arr::wrap(config('database.destructive_operations.allowed_environments', []));
        if ($requiredEnvironment !== null) {
            $allowedEnvironments = [$requiredEnvironment];
        }

        if (! in_array($environment, $allowedEnvironments, true)) {
            return "APP_ENV '{$environment}' não é permitido";
        }

        $allowedConnections = Arr::wrap(config('database.destructive_operations.allowed_connections', []));
        if (! in_array($connectionName, $allowedConnections, true)) {
            return "conexão '{$connectionName}' não é permitida";
        }

        $allowedDatabases = (array) config('database.destructive_operations.allowed_databases', []);
        $environmentDatabases = Arr::wrap($allowedDatabases[$environment] ?? []);
        if (! in_array($database, $environmentDatabases, true)) {
            return "DB_DATABASE '{$database}' não está na allowlist para '{$environment}'";
        }

        $allowedHosts = Arr::wrap(config('database.destructive_operations.allowed_hosts', []));
        if (! in_array($host, $allowedHosts, true)) {
            return "DB_HOST '{$host}' não está na allowlist";
        }

        $allowedUsernames = Arr::wrap(config('database.destructive_operations.allowed_usernames', []));
        if (! in_array($username, $allowedUsernames, true)) {
            return 'credencial de banco não é uma credencial descartável allowlisted';
        }

        $expectedMarker = (string) ($environment === 'testing' ? 'testing' : 'e2e');
        if ($marker !== $expectedMarker) {
            return 'marcador explícito de banco descartável ausente ou incompatível';
        }

        return null;
    }

    private function containsProductionIndicator(array $identity): bool
    {
        foreach ($identity as $value) {
            if (preg_match('/(^|[_\\-.])(prod|production|live)([_\\-.]|$)/i', (string) $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
