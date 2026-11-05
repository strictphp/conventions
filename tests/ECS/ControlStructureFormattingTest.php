<?php declare(strict_types=1);

namespace StrictPhp\ConventionsTests\ECS;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ControlStructureFormattingTest extends TestCase
{
    public function testEcsFormatsControlStructureSpacingAndIsIdempotent(): void {
        $path = tempnam(sys_get_temp_dir(), 'ecs-control-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, <<<'PHP'
<?php
class NextLineClass
{
    public function nextLineMethod()
    {
        if ($value) { methodFirst(); methodSecond(); }
    }
}
class SameLineClass
{
    public function sameLineMethod() { if ($value) methodCall(); }
}
function nextLineFunction()
{
    if ($value) legacyCall();
}
function process($value) {
    if ($value === 7) { logValue(7); }if ($value === 8) { logValue(8); }finishCompact();
    initialize();
    if ($value) return 1;
    if ($value === 9) {}
    if ($value === 10) { first(); second(); }
    if ($value === 11) { /* keep */ }
    if ($value === 12) { keep(); } /* attached */ else { other(); }
    if ($value === null) { logValue(2); } elseif ($value === false) { logValue(3); } else { logValue(4); }
    try { work(); } catch (RuntimeException $exception) { recover(); } finally { finish(); }
    do { tick(); } while (again());
    done();
}
PHP);

            $this->runEcs($path);

            $expected = <<<'PHP'
<?php declare(strict_types=1);

class NextLineClass
{
    public function nextLineMethod()
    {
        if ($value) {
            methodFirst();
            methodSecond();
        }
    }
}
class SameLineClass
{
    public function sameLineMethod() { if ($value) {
            methodCall();
        }
    }
}
function nextLineFunction()
{
    if ($value) {
        legacyCall();
    }
}
function process($value) {
    if ($value === 7) {
        logValue(7);
    }

    if ($value === 8) {
        logValue(8);
    }

    finishCompact();
    initialize();

    if ($value) {
        return 1;
    }

    if ($value === 9) {
    }

    if ($value === 10) {
        first();
        second();
    }

    if ($value === 11) {
        /* keep */
    }

    if ($value === 12) {
        keep();
    } /* attached */ else {
        other();
    }

    if ($value === null) {
        logValue(2);
    } elseif ($value === false) {
        logValue(3);
    } else {
        logValue(4);
    }

    try {
        work();
    } catch (RuntimeException $exception) {
        recover();
    } finally {
        finish();
    }

    do {
        tick();
    } while (again());

    done();
}
PHP;
            self::assertSame($expected, file_get_contents($path));

            $this->runEcs($path);

            self::assertSame($expected, file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    private function runEcs(string $path): void {
        $projectRoot = dirname(__DIR__, 2);
        $process = new Process([
            $projectRoot . '/vendor/bin/ecs',
            'check',
            '--fix',
            '--config=' . $projectRoot . '/extension-ecs.php',
            '--paths=' . $path,
        ], $projectRoot);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
    }
}
