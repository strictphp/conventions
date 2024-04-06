<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Strict\Rector\BooleanNot\BooleanInBooleanNotRuleFixerRector;

return RectorConfig::configure()
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, codingStyle: true)
    ->withImportNames()
    ->withConfiguredRule(BooleanInBooleanNotRuleFixerRector::class, [
        BooleanInBooleanNotRuleFixerRector::TREAT_AS_NON_EMPTY => false,
    ])
    ->withSkip([
        NewlineAfterStatementRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class,
    ]);

