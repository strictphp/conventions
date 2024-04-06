<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withParallel()
    ->withPreparedSets(common: true, symplify: true, cleanCode: true)
    ->withConfiguredRule(
        checkerClass: ClassAttributesSeparationFixer::class,
        configuration: [
            'elements' => [
                'const' => 'only_if_meta',
                'property' => 'one',
                'method' => 'one',
            ],
        ]
    )
    ->withSkip([YodaStyleFixer::class]);
