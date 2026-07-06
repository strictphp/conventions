<?php
declare(strict_types=1);

namespace StrictPhp\ConventionsTests;

use PHPUnit\Framework\TestCase;

class EmptyTest extends TestCase
{
    public function testOk(): void
    {
        /** @phpstan-ignore-next-line */
        $this->assertTrue(true);
    }
}
