<?php

/**
 * Guard against fat controllers.
 *
 * Controllers must authorize + delegate to an Action and return a Resource.
 * They must NOT query models, scope by tenant_id, abort() inline, or wrap
 * logic in try/catch — that belongs in Actions and the exception handler.
 */
it('keeps controllers lean — no inline queries, tenant scoping, abort() or try/catch', function (): void {
    $controllersDirectory = base_path('app/Modules');

    /** @var array<string> $phpFiles */
    $phpFiles = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersDirectory));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo
            && $file->getExtension() === 'php'
            && str_contains($file->getRealPath(), '/Http/Controllers/')) {
            $phpFiles[] = $file->getRealPath();
        }
    }

    expect($phpFiles)->not->toBeEmpty('Nenhum controller encontrado em app/Modules/*/Http/Controllers — scanner provavelmente quebrou.');

    $forbiddenPatterns = [
        'inline query' => '/::query\(\)/',
        'tenant scoping' => "/->\s*where\(\s*['\"]tenant_id['\"]/",
        'inline abort()' => '/\babort\(/',
        'try/catch' => '/\btry\s*\{/',
    ];

    /** @var array<string> $violations */
    $violations = [];

    foreach ($phpFiles as $filePath) {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            continue;
        }

        foreach ($forbiddenPatterns as $label => $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $violations[] = basename($filePath).": {$label}";
            }
        }
    }

    expect($violations)->toBeEmpty(
        'These controllers carry logic that belongs in Actions: '.implode(', ', $violations)
    );
});
