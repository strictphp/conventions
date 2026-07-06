<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use StrictPhp\Conventions\Rector\Renaming\CarbonCreateToCreateStrictRector;

return RectorConfig::configure()
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, codingStyle: true)
    ->withImportNames()
    ->withRules([
        CarbonCreateToCreateStrictRector::class,
    ])
    ->withSkip([
        NewlineAfterStatementRector::class,
        NewlineBetweenClassLikeStmtsRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class
    ]);

