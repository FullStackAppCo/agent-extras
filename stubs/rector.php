<?php

declare(strict_types=1);

use FullStackAppCo\RectorExtras\Rules\ArtisanExitCodeAssertionRector;
use FullStackAppCo\RectorExtras\Rules\HelperFunctionToFacadeRector;
use FullStackAppCo\RectorExtras\Rules\HttpStatusAssertionRector;
use FullStackAppCo\RectorExtras\Rules\NativeStringFunctionToStrRector;
use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/bootstrap/cache',
        ClosureToArrowFunctionRector::class,
    ])
    ->withPhpSets()
    ->withRules([
        ArtisanExitCodeAssertionRector::class,
        DeclareStrictTypesRector::class,
        HelperFunctionToFacadeRector::class,
        HttpStatusAssertionRector::class,
        NativeStringFunctionToStrRector::class,
    ])
    ->withImportNames()
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
