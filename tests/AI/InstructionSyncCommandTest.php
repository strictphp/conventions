<?php declare(strict_types=1);

namespace StrictPhp\ConventionsTests\AI;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

final class InstructionSyncCommandTest extends TestCase
{
    public function testCommandChecksWithoutWritingAndSupportsProjectPathsWithSpaces(): void
    {
        $project = sys_get_temp_dir() . '/strictphp consumer ' . bin2hex(random_bytes(8));
        mkdir($project);
        file_put_contents($project . '/composer.json', '{}');
        $command = dirname(__DIR__, 2) . '/bin/strictphp-sync-ai';

        try {
            $help = new Process([PHP_BINARY, $command, '--help']);
            $help->mustRun();
            self::assertStringStartsWith('Usage: strictphp-sync-ai', $help->getOutput());
            $check = new Process([PHP_BINARY, $command, '--project', $project, '--check']);
            self::assertSame(1, $check->run());
            self::assertDirectoryDoesNotExist($project . '/.agents');
            $sync = new Process([PHP_BINARY, $command, '--project', $project]);
            $sync->mustRun();
            self::assertFileExists($project . '/.agents/skills/strictphp/SKILL.md');
            self::assertSame(0, $check->run());
            self::assertSame('', $check->getOutput());
            unlink($project . '/.agents/skills/strictphp/SKILL.md');
            self::assertSame(1, $check->run());
            self::assertFileDoesNotExist($project . '/.agents/skills/strictphp/SKILL.md');
            $sync->mustRun();
            self::assertSame(0, $check->run());
            $invalid = new Process([PHP_BINARY, $command, '--project']);
            self::assertSame(1, $invalid->run());
            self::assertStringContainsString('Unknown or incomplete argument', $invalid->getErrorOutput());
        } finally {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($iterator as $entry) {
                self::assertInstanceOf(SplFileInfo::class, $entry);

                if ($entry->isDir()) {
                    rmdir($entry->getPathname());
                } else {
                    unlink($entry->getPathname());
                }
            }

            rmdir($project);
        }
    }
}
