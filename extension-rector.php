<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, codingStyle: true)
    ->withImportNames()
    ->withSkip([
        NewlineAfterStatementRector::class,
        NewlineBetweenClassLikeStmtsRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class
    ]);

