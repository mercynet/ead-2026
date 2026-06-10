<?php

/**
 * Guard against permission drift.
 *
 * Scans app/ for every permission string used in authorization checks and
 * asserts that each one is declared in config('permissions').
 * No database required — this test reads files and config only.
 */
it('every permission string used in app/ is declared in config/permissions.php', function (): void {
    $canonicalPermissions = array_keys(config('permissions'));

    $patterns = [
        // getAllPermissions()->contains('name', '<X>')
        "/getAllPermissions\(\)\s*->\s*contains\(\s*'name'\s*,\s*'([^']+)'\s*\)/",
        // hasPermissionTo('<X>')
        "/hasPermissionTo\(\s*'([^']+)'\s*\)/",
        // ->can('<X>'  (permission-style strings only — domain.resource.action)
        "/->can\(\s*'([a-z]+\.[a-z_]+\.[a-z_\-]+)'\s*/",
        // hasAnyPermission('<X>', '<Y>', ...)
        "/hasAnyPermission\(([^)]+)\)/",
    ];

    $appDirectory = base_path('app');

    /** @var array<string> $phpFiles */
    $phpFiles = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDirectory));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getRealPath();
        }
    }

    /** @var array<string> $foundPermissions */
    $foundPermissions = [];

    foreach ($phpFiles as $filePath) {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches)) {
                if (str_contains($pattern, 'hasAnyPermission')) {
                    // Extract individual quoted strings from argument list
                    foreach ($matches[1] as $argList) {
                        if (preg_match_all("/'([^']+)'/", $argList, $argMatches)) {
                            foreach ($argMatches[1] as $perm) {
                                $foundPermissions[] = $perm;
                            }
                        }
                    }
                } else {
                    foreach ($matches[1] as $perm) {
                        $foundPermissions[] = $perm;
                    }
                }
            }
        }
    }

    $foundPermissions = array_unique($foundPermissions);

    // Sanity: o scan precisa encontrar permissions (senão um regex quebrado passaria trivial).
    expect($foundPermissions)->not->toBeEmpty('Nenhuma permission encontrada em app/ — scanner provavelmente quebrou.');

    $orphans = array_values(array_filter(
        $foundPermissions,
        fn (string $perm): bool => ! in_array($perm, $canonicalPermissions, true),
    ));

    expect($orphans)
        ->toBeEmpty(
            'The following permission strings are used in app/ but are NOT declared in config/permissions.php: '
            .implode(', ', $orphans)
        );
});
