<?php

/**
 * Guard against floating-point money.
 *
 * Scans database/migrations for column definitions whose name implies a
 * monetary value and asserts none is declared as float/double/decimal.
 * Money must travel as integer cents (see api-conventions.md). No DB required.
 */
it('declares every monetary column as an integer type, never float/double/decimal', function (): void {
    $migrationsDirectory = base_path('database/migrations');

    /** @var array<string> $phpFiles */
    $phpFiles = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($migrationsDirectory));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getRealPath();
        }
    }

    // Column names that carry money. `cents` is the canonical suffix; the others
    // catch legacy/accidental naming before it ships.
    $moneyNamePattern = '/(price|amount|cost|fee|balance|subtotal|discount|total|cents)/i';

    // Floating column builders that must never hold money.
    $floatTypePattern = "/->\s*(float|double|decimal|unsignedDecimal)\s*\(\s*'([^']+)'/";

    /** @var array<string> $violations */
    $violations = [];

    // Sanity counter: the scanner must find at least one real `_cents` column,
    // otherwise a broken regex would pass this test trivially.
    $centsColumnsSeen = 0;

    foreach ($phpFiles as $filePath) {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            continue;
        }

        if (preg_match_all("/->\s*\w+\s*\(\s*'([^']*_cents)'/", $contents, $centsMatches)) {
            $centsColumnsSeen += count($centsMatches[1]);
        }

        if (preg_match_all($floatTypePattern, $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $columnName = $match[2];

                if (preg_match($moneyNamePattern, $columnName) === 1) {
                    $violations[] = basename($filePath).": {$match[1]}('{$columnName}')";
                }
            }
        }
    }

    expect($centsColumnsSeen)->toBeGreaterThan(0, 'No `_cents` columns found — the migration scanner is probably broken.');

    expect($violations)->toBeEmpty(
        'Monetary columns must be integer cents, never float/double/decimal: '.implode(', ', $violations)
    );
});
