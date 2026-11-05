<?php declare(strict_types=1);

namespace StrictPhp\Conventions\Architecture;

final class ClassMemberOrder
{
    public const array Default = ['use_trait', 'case', 'constant', 'property_static', 'property', 'construct', 'method'];
}
