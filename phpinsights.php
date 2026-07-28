<?php

declare(strict_types=1);

use NunoMaduro\PhpInsights\Domain\Insights\AvoidStaticCall;
use NunoMaduro\PhpInsights\Domain\Insights\CyclomaticComplexityIsHigh;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenDefineFunctions;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenFinalClasses;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenNormalClasses;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenTraits;
use NunoMaduro\PhpInsights\Domain\Insights\LiskovSubstitutionPrinciple;
use NunoMaduro\PhpInsights\Domain\Insights\UnusedParameter;
use NunoMaduro\PhpInsights\Domain\Insights\UsesEval;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Files\LineLengthSniff;
use SlevomatCodingStandard\Sniffs\Classes\SuperfluousExceptionNamingSniff;
use SlevomatCodingStandard\Sniffs\Classes\SuperfluousInterfaceNamingSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DeclareStrictTypesSniff;

return [
    'preset' => 'laravel',
    'ide' => 'phpstorm',
    'exclude' => [
        'bootstrap/cache',
        'storage',
        'vendor',
    ],
    'remove' => [
        ForbiddenTraits::class,
        ForbiddenFinalClasses::class,
        // Classes não são forçadas a final/abstract — convenção Laravel do projeto
        // (models/controllers/actions não são final). Sem isto o score de
        // architecture despenca ~7pts com centenas de falsos-positivos.
        ForbiddenNormalClasses::class,
        // O projeto não usa declare(strict_types=1) em app/ — não penalizar.
        DeclareStrictTypesSniff::class,
        // Laravel/Pest usam helpers globais (route(), config(), helpers de teste).
        ForbiddenDefineFunctions::class,
        // Formatação é responsabilidade do Pint (gate: pint + git diff --exit-code);
        // o Pint não quebra linhas, então o line-length do phpinsights é ruído duplicado.
        LineLengthSniff::class,
        // Naming pedante que briga com a convenção universal PHP (*Exception,
        // *Interface) — sinal sem valor para este projeto.
        SuperfluousExceptionNamingSniff::class,
        SuperfluousInterfaceNamingSniff::class,
        LiskovSubstitutionPrinciple::class,
        UsesEval::class,
        AvoidStaticCall::class,
        UnusedParameter::class,
    ],
    'config' => [
        CyclomaticComplexityIsHigh::class => [
            'maxComplexity' => 8,
        ],
    ],
    'requirements' => [
        'min-quality' => 83,
        'min-complexity' => 85,
        'min-architecture' => 75,
        'min-style' => 88,
        // Auditoria de dependências NÃO vive aqui: qualquer advisory novo (ex.: Guzzle)
        // derrubava o qa:gate inteiro mesmo sem relação com o código. Fonte única de
        // supply-chain é `composer qa:deps` (security:audit-deps + composer audit --locked).
        'disable-security-check' => true,
    ],
];
