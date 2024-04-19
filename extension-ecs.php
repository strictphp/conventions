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
                'const' => 'none',
                'property' => 'none',
                'method' => 'one',
                'trait_import' => 'none',
                'case' => 'none',
            ],
        ])
    ->withConfiguredRule(
        checkerClass: TrailingCommaInMultilineFixer::class,
        configuration: [
            'after_heredoc' => true,
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ])
    ->withSkip([
        PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer::class,
        PhpCsFixer\Fixer\PhpTag\LinebreakAfterOpeningTagFixer::class,
        YodaStyleFixer::class,
    ]);
