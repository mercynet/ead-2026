<?php

/**
 * Tenant scoping (ADR-004).
 *
 * Filtragem por tenant é explícita: arquivo em app/ que consulta um model
 * tenant-scoped estaticamente (query/where/find/create/...) precisa referenciar
 * `tenant_id` no próprio arquivo — ou constar na allowlist com justificativa.
 *
 * Heurística de scan textual (não semântica): pega o modo de falha "esqueci o
 * filtro por completo"; isolamento fino por endpoint vive na suite de Feature e
 * no TenantIsolationSmokeTest (HTTP).
 */
it('files querying tenant-scoped models reference tenant_id explicitly', function (): void {
    $appPath = base_path('app');

    /** @var array<string> $tenantScopedModels nomes de classe (basename) de models com tenant_id */
    $tenantScopedModels = [];

    foreach (glob(base_path('app/Modules/*/Models/*.php')) ?: [] as $modelFile) {
        $contents = file_get_contents($modelFile);

        if ($contents !== false && str_contains($contents, "'tenant_id'")) {
            $tenantScopedModels[] = basename($modelFile, '.php');
        }
    }

    expect($tenantScopedModels)->not->toBeEmpty('Nenhum model tenant-scoped encontrado — scanner provavelmente quebrou.');

    /** @var array<string, string> $allowlist arquivo (relativo a app/) => justificativa */
    $allowlist = [
        'Modules/Assessment/Actions/Certificate/VerifyCertificateAction.php' => 'verificação pública por código único — endpoint sem tenant por design',
        'Modules/Assessment/Actions/Attempt/ShowAttemptAction.php' => 'escopo por user_id do usuário autenticado — mais estrito que tenant (identidade é tenant-scoped)',
        'Modules/Assessment/Actions/Attempt/FinishAttemptAction.php' => 'escopo por user_id do usuário autenticado — mais estrito que tenant (identidade é tenant-scoped)',
        'Modules/Assessment/Actions/Certificate/ShowCertificateAction.php' => 'escopo por user_id do usuário autenticado — mais estrito que tenant (identidade é tenant-scoped)',
        'Modules/Assessment/Actions/Certificate/ListCertificatesAction.php' => 'escopo por user_id do usuário autenticado — mais estrito que tenant (identidade é tenant-scoped)',
    ];

    /** @var array<string> $tenantScopeMarkers presença de qualquer um satisfaz o filtro explícito */
    $tenantScopeMarkers = ['tenant_id', 'forTenantUserCourse('];

    $staticCalls = implode('|', ['query', 'where', 'find', 'findOrFail', 'first', 'firstWhere', 'create', 'firstOrCreate', 'updateOrCreate', 'count', 'exists', 'with']);
    $pattern = '/\b('.implode('|', array_unique($tenantScopedModels)).')::('.$staticCalls.')\(/';

    /** @var array<string> $violations */
    $violations = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            continue;
        }

        $relativePath = ltrim(str_replace($appPath, '', $file->getRealPath()), '/');

        if (array_key_exists($relativePath, $allowlist)) {
            continue;
        }

        if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER) === 0) {
            continue;
        }

        if (array_any($tenantScopeMarkers, fn (string $marker): bool => str_contains($contents, $marker))) {
            continue;
        }

        $calls = array_unique(array_map(fn (array $m): string => $m[1].'::'.$m[2], $matches));
        $violations[] = $relativePath.' → '.implode(', ', $calls);
    }

    expect($violations)->toBeEmpty(
        "Query em model tenant-scoped sem filtro explícito de tenant_id (ADR-004; allowlist para exceção justificada):\n"
        .implode("\n", $violations)
    );
});
