<?php declare(strict_types=1);

namespace StrictPhp\ConventionsTests;

use PharData;
use PharFileInfo;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ReleaseArchiveTest extends TestCase
{
    public function testComposerAndGitArchivesContainRunnableRuntimeFiles(): void
    {
        $root = dirname(__DIR__);
        $temporary = sys_get_temp_dir() . '/strictphp-release-' . bin2hex(random_bytes(8));
        $snapshot = $temporary . '/snapshot';
        self::assertTrue(mkdir($snapshot, 0755, true));

        try {
            $required = [];
            foreach (['bin', 'src', 'resources', 'doc', 'docs'] as $directory) {
                $this->copyDirectory($root . '/' . $directory, $snapshot . '/' . $directory);
                foreach ($this->files($snapshot . '/' . $directory) as $file) {
                    $required[] = $directory . '/' . substr($file, strlen($snapshot . '/' . $directory) + 1);
                }
            }

            foreach (['composer.json', 'LICENSE.md', 'README.md', 'extension.neon', 'extension-ecs.php', 'extension-rector.php'] as $file) {
                self::assertTrue(copy($root . '/' . $file, $snapshot . '/' . $file));
                $required[] = $file;
            }

            self::assertTrue(copy($root . '/.gitattributes', $snapshot . '/.gitattributes'));
            self::assertTrue(mkdir($snapshot . '/tests', 0755, true));
            self::assertNotFalse(file_put_contents($snapshot . '/tests/release-test.txt', 'Development files must not ship.'));

            $this->runCommand(['git', 'init', '--quiet', $snapshot], $root);
            $this->runCommand(['git', 'add', '--force', '.'], $snapshot);
            $this->runCommand(['git', 'update-index', '--chmod=+x', 'bin/strictphp-sync-ai'], $snapshot);
            $tree = trim($this->runCommand(['git', 'write-tree'], $snapshot));
            $gitArchive = $temporary . '/git.tar';
            $this->runCommand(['git', 'archive', '--format=tar', '--output=' . $gitArchive, $tree], $snapshot);

            $composer = (new ExecutableFinder())->find('composer');
            self::assertNotNull($composer, 'Composer is required to validate release archives.');
            $this->runCommand([
                $composer, 'archive', '--format=tar', '--dir=' . $temporary, '--file=composer',
                '--no-interaction', '--no-plugins', '--no-scripts',
            ], $snapshot);

            foreach ([$gitArchive, $temporary . '/composer.tar'] as $archivePath) {
                $archive = new PharData($archivePath);
                $files = $this->archiveFiles($archive, $archivePath);
                self::assertSame([], array_values(array_diff($required, $files)), $archivePath . ' is missing runtime files.');
                self::assertNotContains('tests/release-test.txt', $files, $archivePath . ' contains development tests.');
                $executablePermissions = $this->entrypointPermissions($archive, $archivePath);
                self::assertNotNull($executablePermissions, $archivePath . ' has no StrictPHP command.');

                if (str_ends_with($archivePath, '/git.tar')) {
                    self::assertNotSame(0, $executablePermissions & 0111, $archivePath . ' does not mark the command executable.');
                }

                $extract = $temporary . '/' . basename($archivePath, '.tar');
                self::assertTrue(mkdir($extract, 0755, true));
                $archive->extractTo($extract);
                $entrypoint = $extract . '/bin/strictphp-sync-ai';
                self::assertFileExists($entrypoint, $archivePath . ' does not contain the StrictPHP command.');
                $command = ($executablePermissions & 0111) !== 0 ? [$entrypoint, '--help'] : [PHP_BINARY, $entrypoint, '--help'];

                $help = new Process($command, dirname($entrypoint));
                $help->setTimeout(30);
                $help->mustRun();
                self::assertStringContainsString('Usage: strictphp-sync-ai', $help->getOutput());

                $packageRoot = $extract;
                $consumer = $temporary . '/consumer-' . basename($archivePath, '.tar');
                self::assertTrue(mkdir($consumer, 0755, true));
                self::assertNotFalse(file_put_contents($consumer . '/composer.json', "{\"name\":\"test/consumer\"}\n"));
                $commandPrefix = ($executablePermissions & 0111) !== 0 ? [$entrypoint] : [PHP_BINARY, $entrypoint];
                $this->runCommand([...$commandPrefix, '--project', $consumer], $packageRoot);
                $this->runCommand([...$commandPrefix, '--check', '--project', $consumer], $packageRoot);

                $managedSkill = $consumer . '/.agents/skills/strictphp/SKILL.md';
                self::assertFileExists($managedSkill);
                self::assertTrue(unlink($managedSkill));
                $this->runCommand([...$commandPrefix, '--check', '--project', $consumer], $packageRoot, 1);
                $this->runCommand([...$commandPrefix, '--project', $consumer], $packageRoot);
                self::assertFileExists($managedSkill);
                $this->runCommand([...$commandPrefix, '--check', '--project', $consumer], $packageRoot);
            }
        } finally {
            $this->removeDirectory($temporary);
        }
    }

    /**
     * @return list<string>
     */
    private function files(string $directory): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function archiveFiles(PharData $archive, string $archivePath): array
    {
        $paths = [];
        foreach (new RecursiveIteratorIterator($archive) as $file) {
            self::assertInstanceOf(PharFileInfo::class, $file);
            $path = substr($file->getPathname(), strlen('phar://' . $archivePath . '/'));
            $paths[] = $path;
        }

        return $paths;
    }

    private function entrypointPermissions(PharData $archive, string $archivePath): ?int
    {
        foreach (new RecursiveIteratorIterator($archive) as $file) {
            self::assertInstanceOf(PharFileInfo::class, $file);

            if (str_ends_with($file->getPathname(), '/bin/strictphp-sync-ai')) {
                return $file->getPerms();
            }
        }

        return null;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        self::assertTrue(mkdir($destination, 0755, true));
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
            self::assertInstanceOf(SplFileInfo::class, $item);
            $target = $destination . '/' . substr($item->getPathname(), strlen($source) + 1);

            if ($item->isDir()) {
                self::assertTrue(mkdir($target, 0755, true));
            } else {
                self::assertTrue(copy($item->getPathname(), $target));

                if (str_starts_with($target, $destination . '/bin/')) {
                    self::assertTrue(chmod($target, fileperms($item->getPathname()) & 0777));
                }
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (is_dir($directory) === false) {
            return;
        }

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory . '/' . $name;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments, string $directory, int $exitCode = 0): string
    {
        $process = new Process($arguments, $directory);
        $process->setTimeout(60);
        $process->run();
        self::assertSame($exitCode, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());

        return $process->getOutput();
    }
}
