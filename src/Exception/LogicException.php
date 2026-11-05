<?php declare(strict_types=1);

namespace StrictPhp\Conventions\Exception;

use Throwable;

final class LogicException extends \LogicException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        $code = $previous?->getCode() ?? 0;
        parent::__construct($message, is_int($code) ? $code : 0, $previous);
    }
}
