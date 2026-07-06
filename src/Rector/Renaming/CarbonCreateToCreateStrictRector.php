<?php

declare(strict_types=1);

namespace StrictPhp\Conventions\Rector\Renaming;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class CarbonCreateToCreateStrictRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change Carbon::create() to Carbon::createStrict()',
            [
                new CodeSample(
                    <<<'CODE_BEFORE'
Carbon\Carbon::create(year: 2023, day: 20);
CODE_BEFORE
                    ,
                    <<<'CODE_AFTER'
Carbon\Carbon::createStrict(year: 2023, day: 20);
CODE_AFTER
,
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        // We are only interested in static method calls (e.g., Class::method())
        return [StaticCall::class];
    }

    /**
     * @param StaticCall $node
     */
    public function refactor(Node $node): ?Node
    {
        // 1. Check if the class is Carbon or CarbonImmutable
        $isCarbon = $this->isObjectType($node->class, new ObjectType(Carbon::class));
        $isCarbonImmutable = $this->isObjectType($node->class, new ObjectType(CarbonImmutable::class));

        if (! $isCarbon && ! $isCarbonImmutable) {
            return null;
        }

        // 2. Check if the method being called is exactly 'create'
        if (! $this->isName($node->name, 'create')) {
            return null;
        }

        // 3. Perform the refactor by renaming the method identifier
        $node->name = new Identifier('createStrict');

        // Return the modified node to let Rector know we changed it
        return $node;
    }
}
