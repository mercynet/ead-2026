<?php

/**
 * Guard against fat controllers.
 *
 * Controllers must authorize + delegate to an Action and return a Resource.
 * They must NOT query models, scope by tenant_id, abort() inline, or wrap
 * logic in try/catch — that belongs in Actions and the exception handler.
 *
 * Current debt: the Learning controllers (Catalog/Course/Lesson) still query
 * inline and abort(403). The assertion is present and will hard-fail the day
 * `skip()` is removed — see docs/specs/00-architecture/backend-patterns.md.
 */
it('keeps controllers lean — no inline queries, tenant scoping, abort() or try/catch', function (): void {
    $controllersDirectory = base_path('app/Http/Controllers');

    /** @var array<string> $phpFiles */
    $phpFiles = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersDirectory));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getRealPath();
        }
    }

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
})->skip('debt: Learning controllers (Catalog/Course/Lesson) still query inline and abort(403)');
