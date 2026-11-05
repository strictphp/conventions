<?php

declare(strict_types=1);

namespace StrictPhp\ConventionsTests\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StrictPhp\Conventions\Exception\LogicException;
use StrictPhp\Conventions\Exception\RuntimeException;
use Throwable;

final class ExceptionTest extends TestCase
{
    #[DataProvider('providePreviousExceptions')]
    public function testWrappingKeepsTheCauseAndUsesAnIntegerCode(?Throwable $previous, int $expectedCode): void
    {
        $exceptions = [
            new LogicException('Wrapper message', $previous),
            new class('Wrapper message', $previous) extends RuntimeException {},
        ];

        foreach ($exceptions as $exception) {
            self::assertSame('Wrapper message', $exception->getMessage());
            self::assertSame($expectedCode, $exception->getCode());
            self::assertSame($previous, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{?Throwable, int}>
     */
    public static function providePreviousExceptions(): iterable
    {
        yield 'no cause' => [null, 0];
        yield 'integer code' => [new \RuntimeException('Original', 42), 42];
        yield 'string code' => [new class() extends \RuntimeException {
            public function __construct()
            {
                $this->code = 'HY000';
            }
        }, 0];
    }
}
