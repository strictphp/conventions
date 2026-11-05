<?php declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureBracesFixer;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureContinuationPositionFixer;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use PhpCsFixer\Fixer\Whitespace\IndentationTypeFixer;
use PhpCsFixer\Fixer\Whitespace\StatementIndentationFixer;
use StrictPhp\Conventions\Architecture\ClassMemberOrder;
use StrictPhp\Conventions\ECS\BlankLineAfterControlStructureFixer;
use StrictPhp\Conventions\ECS\FileHeaderFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withParallel()
    ->withPreparedSets(common: true, cleanCode: true)
    ->withRules([
        IndentationTypeFixer::class,
        FileHeaderFixer::class,
        BlankLineAfterControlStructureFixer::class,
        ControlStructureBracesFixer::class,
        ControlStructureContinuationPositionFixer::class,
        StatementIndentationFixer::class,
    ])
    ->withConfiguredRule(OrderedClassElementsFixer::class, [
        'order' => ClassMemberOrder::Default,
        'sort_algorithm' => 'none',
    ])
    ->withConfiguredRule(BlankLineBeforeStatementFixer::class, [
        'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try', 'if'],
    ])
    ->withConfiguredRule(ControlStructureContinuationPositionFixer::class, [
        'position' => 'same_line',
    ])
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
        ],
    )
    ->withConfiguredRule(
        checkerClass: TrailingCommaInMultilineFixer::class,
        configuration: [
            'after_heredoc' => true,
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ],
    )
    ->withSkip([
        PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer::class,
        PhpCsFixer\Fixer\PhpTag\LinebreakAfterOpeningTagFixer::class,
        YodaStyleFixer::class,
    ]);
