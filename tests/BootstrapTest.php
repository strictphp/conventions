<?php declare(strict_types=1);

namespace StrictPhp\ConventionsTests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BootstrapTest extends TestCase
{
    public function testParserCanRunBeforeRectorTestSetup(): void
    {
        $script = <<<'PHP'
require 'tests/bootstrap.php';

$parser = (new PhpParser\ParserFactory())->createForHostVersion();
$parser->parse('<?php use DateTime;');

// Rector test setup loads this file after coverage has parsed source files.
require_once 'vendor/rector/rector/preload.php';
echo 'ok';
PHP;
        $process = new Process([PHP_BINARY, '-r', $script], dirname(__DIR__));
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        self::assertSame('ok', $process->getOutput());
    }
}
