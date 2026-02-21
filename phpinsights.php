<?php

declare(strict_types=1);

use NunoMaduro\PhpInsights\Domain\Insights\AvoidStaticCall;
use NunoMaduro\PhpInsights\Domain\Insights\CyclomaticComplexityIsHigh;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenFinalClasses;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenTraits;
use NunoMaduro\PhpInsights\Domain\Insights\LiskovSubstitutionPrinciple;
use NunoMaduro\PhpInsights\Domain\Insights\UnusedParameter;
use NunoMaduro\PhpInsights\Domain\Insights\UsesEval;

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
        'min-quality' => 85,
        'min-complexity' => 85,
        'min-architecture' => 80,
        'min-style' => 95,
    ],
];
