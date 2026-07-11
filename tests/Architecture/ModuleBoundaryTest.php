<?php

/**
 * Module boundary (AGENTS.md invariante 11).
 *
 * Um módulo não importa interno de outro módulo. Exceções:
 *  - Shared kernel: `App\Modules\Core\Models` e `App\Modules\Core\Enums`
 *    (identidade/tenancy) podem ser importados por qualquer módulo; Core,
 *    por sua vez, não importa nada de outros módulos.
 *  - Fronteiras públicas: `Events` e `Contracts` de qualquer módulo.
 *  - `App\Shared` e `App\Support` são livres (não são módulos).
 *
 * Dívida conhecida (relações Eloquent cross-module herdadas do código flat)
 * vive em $knownDebt: o teste impede que ela CRESÇA; o alvo é convertê-la em
 * Domain Events/Contracts — ver backend-patterns.md.
 */
it('modules only depend on other modules via shared kernel, Events or Contracts', function (): void {
    $modulesPath = base_path('app/Modules');

    /** @var array<string> $modules */
    $modules = array_map('basename', glob($modulesPath.'/*', GLOB_ONLYDIR) ?: []);

    expect($modules)->not->toBeEmpty('Nenhum módulo encontrado em app/Modules — scanner provavelmente quebrou.');

    /** @var array<string, array<string>> $knownDebt arquivo (relativo a app/Modules) => imports tolerados */
    $knownDebt = [
        'Core/Models/User.php' => [
            'App\Modules\Assessment\Models\Certificate',
            'App\Modules\Assessment\Models\QuizAttempt',
        ],
        'Learning/Models/Course.php' => [
            'App\Modules\Assessment\Models\Certificate',
        ],
        'Assessment/Models/Certificate.php' => [
            'App\Modules\Learning\Models\Course',
            'App\Modules\Learning\Models\Enrollment',
        ],
        'Assessment/Models/QuizQuestion.php' => [
            'App\Modules\Learning\Models\Category',
        ],
        'Assessment/Actions/Attempt/StartAttemptAction.php' => [
            'App\Modules\Learning\Models\Course',
            'App\Modules\Learning\Models\CourseModule',
        ],
        'Assessment/Actions/Questionnaire/StoreQuestionnaireAction.php' => [
            'App\Modules\Learning\Models\Course',
            'App\Modules\Learning\Models\Lesson',
        ],
    ];

    /** @var array<string> $violations */
    $violations = [];

    foreach ($modules as $module) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulesPath.'/'.$module));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getRealPath());

            if ($contents === false) {
                continue;
            }

            preg_match_all('/^use (App\\\\Modules\\\\(\w+)\\\\(\w+)[^;]*);/m', $contents, $matches, PREG_SET_ORDER);

            $relativePath = ltrim(str_replace($modulesPath, '', $file->getRealPath()), '/');

            foreach ($matches as $match) {
                [, $importedClass, $targetModule, $firstSegment] = $match;

                if ($targetModule === $module) {
                    continue;
                }

                if ($targetModule === 'Core' && in_array($firstSegment, ['Models', 'Enums'], true)) {
                    continue;
                }

                if (in_array($firstSegment, ['Events', 'Contracts'], true)) {
                    continue;
                }

                if (in_array($importedClass, $knownDebt[$relativePath] ?? [], true)) {
                    continue;
                }

                $violations[] = $relativePath.' → '.$importedClass;
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Imports cross-module fora da fronteira (use Domain Event ou Contract; shared kernel é só Core\\Models|Enums):\n"
        .implode("\n", $violations)
    );
});
