<?php

use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Guard the LGPD audit trail (invariant #9).
 *
 * Models holding PII must use the LogsActivity trait, and every PII field
 * must be registered in config/lgpd.php — the canonical PII inventory
 * (see docs/specs/00-architecture/security-privacy-lgpd.md). No DB required.
 *
 * Current debt: config/lgpd.php does not exist yet and no model uses
 * LogsActivity. Assertion is present and hard-fails once `skip()` is removed.
 */
it('audits every PII field declared in config/lgpd.php via LogsActivity', function (): void {
    $lgpdConfigPath = config_path('lgpd.php');

    expect(file_exists($lgpdConfigPath))->toBeTrue(
        'config/lgpd.php missing — it is the canonical PII inventory (model => [fields]).'
    );

    /** @var array<class-string, array<string>> $piiInventory */
    $piiInventory = config('lgpd.pii', []);

    expect($piiInventory)->not->toBeEmpty(
        'config/lgpd.php declares no PII — at minimum User (cpf, email, name) must be listed.'
    );

    /** @var array<string> $violations */
    $violations = [];

    foreach ($piiInventory as $modelClass => $piiFields) {
        if (! class_exists($modelClass)) {
            $violations[] = "{$modelClass}: model class not found";

            continue;
        }

        $traits = class_uses_recursive($modelClass);

        if (! in_array(LogsActivity::class, $traits, true)) {
            $violations[] = "{$modelClass}: missing LogsActivity trait";

            continue;
        }

        $model = new $modelClass;
        $loggedAttributes = $model->getActivitylogOptions()->logAttributes ?? [];

        $unlogged = array_values(array_diff($piiFields, $loggedAttributes));

        if ($unlogged !== [] && ! in_array('*', $loggedAttributes, true)) {
            $violations[] = "{$modelClass}: PII fields not logged: ".implode(', ', $unlogged);
        }
    }

    expect($violations)->toBeEmpty(
        'PII audit trail is incomplete: '.implode('; ', $violations)
    );
})->skip('debt: config/lgpd.php does not exist and no model uses LogsActivity yet');
