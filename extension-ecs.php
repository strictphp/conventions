<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
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
        ])
    ->withConfiguredRule(
        checkerClass: TrailingCommaInMultilineFixer::class,
        configuration: [
            'after_heredoc' => true,
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ])
    ->withSkip([YodaStyleFixer::class]);
