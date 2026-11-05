<?php declare(strict_types=1);

namespace StrictPhp\ConventionsTests\AI;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use StrictPhp\Conventions\AI\InstructionSync;

final class InstructionSyncTest extends TestCase
{
    private string $directory;
    private string $project;
    private string $resources;

    protected function setUp(): void {
        $this->directory = sys_get_temp_dir() . '/strictphp-sync-' . bin2hex(random_bytes(8));
        $this->project = $this->directory . '/project';
        $this->resources = $this->directory . '/resources';
        mkdir($this->project, 0755, true);
        mkdir($this->resources . '/strictphp/references', 0755, true);
        file_put_contents($this->project . '/composer.json', '{}');
        file_put_contents($this->project . '/AGENTS.md', "# Local rules\n\nKeep this text.\n");
        file_put_contents($this->resources . '/AGENTS.md', "# PHP conventions\n\nRead the StrictPHP skill first.\n");
        file_put_contents($this->resources . '/strictphp/SKILL.md', "---\nname: strictphp\ndescription: PHP architecture\n---\n");
        file_put_contents($this->resources . '/strictphp/references/data.md', "Use entities.\n");
    }

    protected function tearDown(): void {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo === false) {
                throw new RuntimeException('Expected a filesystem entry.');
            }

            if ($entry->isDir() && $entry->isLink() === false) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($this->directory);
    }

    public function testSyncPreservesLocalRulesAndIsIdempotent(): void {
        $sync = new InstructionSync($this->resources);
        self::assertNotEmpty($sync->execute($this->project));
        self::assertStringStartsWith("# Local rules\n\nKeep this text.\n", (string) file_get_contents($this->project . '/AGENTS.md'));
        self::assertSame([], $sync->execute($this->project));
        self::assertSame([], $sync->execute($this->project, true));
    }

    public function testCheckDoesNotWriteAndDetectsPackageChanges(): void {
        $sync = new InstructionSync($this->resources);
        self::assertNotEmpty($sync->execute($this->project, true));
        self::assertDirectoryDoesNotExist($this->project . '/.agents');
        $sync->execute($this->project);
        file_put_contents($this->resources . '/strictphp/references/data.md', "Updated package rule.\n");
        self::assertContains('.agents/skills/strictphp/references/data.md', $sync->execute($this->project, true));
        self::assertSame("Use entities.\n", file_get_contents($this->project . '/.agents/skills/strictphp/references/data.md'));
        $sync->execute($this->project);
        self::assertSame([], $sync->execute($this->project, true));
    }

    public function testPackageUpgradeRemovesObsoleteOwnedFilesAndPreservesLocalSuffix(): void {
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        file_put_contents($this->project . '/AGENTS.md', "\nLocal exception.\n", FILE_APPEND);
        unlink($this->resources . '/strictphp/references/data.md');
        file_put_contents($this->resources . '/strictphp/SKILL.md', "---\nname: strictphp\ndescription: MUST USE the updated rule.\n---\n");
        $sync->execute($this->project);
        self::assertFileDoesNotExist($this->project . '/.agents/skills/strictphp/references/data.md');
        self::assertStringEndsWith("\nLocal exception.\n", (string) file_get_contents($this->project . '/AGENTS.md'));
        self::assertStringContainsString('MUST USE the updated rule.', (string) file_get_contents($this->project . '/AGENTS.md'));
    }

    public function testSyncRefusesToOverwriteLocalSkillEditsBeforeWritingOtherFiles(): void {
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        file_put_contents($this->project . '/.agents/skills/strictphp/references/data.md', 'Local change');
        file_put_contents($this->resources . '/strictphp/SKILL.md', "---\nname: strictphp\ndescription: Changed package rules\n---\n");

        try {
            $sync->execute($this->project);
            self::fail('Expected a local edit conflict.');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('Locally changed', $runtimeException->getMessage());
            self::assertStringNotContainsString('Changed package rules', (string) file_get_contents($this->project . '/AGENTS.md'));
        }
    }

    public function testUnmanagedSkillIsNotOverwritten(): void {
        mkdir($this->project . '/.agents/skills/strictphp', 0755, true);
        file_put_contents($this->project . '/.agents/skills/strictphp/SKILL.md', 'Existing skill');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not\ managed/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testMalformedMarkersAreNotOverwritten(): void {
        file_put_contents($this->project . '/AGENTS.md', '<!-- strictphp:begin -->');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/markers/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testSymlinkCannotRedirectWritesOutsideProject(): void {
        mkdir($this->directory . '/outside');
        symlink($this->directory . '/outside', $this->project . '/.agents');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/regular\ local\ path/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testManifestCannotDeleteOutsideOwnedDirectory(): void {
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        file_put_contents($this->project . '/.agents/.strictphp-manifest.json', json_encode([
            'version' => 1,
            'files' => [
                '.agents/skills/strictphp/../../../composer.json' => [
                    'package' => 'strictphp/conventions',
                    'hash' => hash('sha256', '{}'),
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid\ instruction\ path/');
        $sync->execute($this->project);
    }

    public function testUpgradeCannotOverwriteAnUnmanagedFile(): void {
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        file_put_contents($this->project . '/.agents/skills/strictphp/references/local.md', 'Local guidance');
        file_put_contents($this->resources . '/strictphp/references/local.md', 'New package guidance');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unmanaged\ file\ conflicts/');
        $sync->execute($this->project);
    }

    public function testDirectoryCannotReplaceAnInstructionFile(): void {
        unlink($this->project . '/AGENTS.md');
        mkdir($this->project . '/AGENTS.md');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/found\ a\ directory/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testUpgradeInstallsAndRemovesBundledSkillsWithoutTouchingOtherSkills(): void
    {
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        mkdir($this->project . '/.agents/skills/personal', 0755, true);
        file_put_contents($this->project . '/.agents/skills/personal/SKILL.md', 'Personal instructions');
        mkdir($this->resources . '/strictphp-models');
        file_put_contents($this->resources . '/strictphp-models/SKILL.md', "---\nname: strictphp-models\ndescription: Model instructions\n---\n");
        self::assertContains('.agents/skills/strictphp-models/SKILL.md', $sync->execute($this->project, true));
        self::assertFileDoesNotExist($this->project . '/.agents/skills/strictphp-models/SKILL.md');
        $sync->execute($this->project);
        self::assertSame("---\nname: strictphp-models\ndescription: Model instructions\n---\n", file_get_contents($this->project . '/.agents/skills/strictphp-models/SKILL.md'));
        self::assertSame([], $sync->execute($this->project));
        unlink($this->resources . '/strictphp-models/SKILL.md');
        rmdir($this->resources . '/strictphp-models');
        $sync->execute($this->project);
        self::assertFileDoesNotExist($this->project . '/.agents/skills/strictphp-models/SKILL.md');
        self::assertSame('Personal instructions', file_get_contents($this->project . '/.agents/skills/personal/SKILL.md'));
    }

    public function testNewBundledSkillCannotTakeOverAnExistingUnmanagedDirectory(): void
    {
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        mkdir($this->resources . '/strictphp-models');
        file_put_contents($this->resources . '/strictphp-models/SKILL.md', "---\nname: strictphp-models\ndescription: Packaged instructions\n---\n");
        mkdir($this->project . '/.agents/skills/strictphp-models', 0755, true);
        file_put_contents($this->project . '/.agents/skills/strictphp-models/local.md', 'My instructions');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not\ managed/');
        $sync->execute($this->project);
    }

    public function testInstalledPackageSkillsAreCheckedUpdatedAndRemoved(): void
    {
        $package = $this->registerPackage('vendor/sdk');
        $sync = new InstructionSync($this->resources);
        $target = '.agents/skills/strictphp-conventions-sdk/SKILL.md';
        self::assertContains($target, $sync->execute($this->project, true));
        self::assertFileDoesNotExist($this->project . '/' . $target);
        $sync->execute($this->project);
        self::assertSame("---\nname: strictphp-conventions-sdk\ndescription: SDK guidance\n---\nSDK guidance", file_get_contents($this->project . '/' . $target));
        self::assertStringContainsString('[strictphp-conventions-sdk](.agents/skills/strictphp-conventions-sdk/SKILL.md)', (string) file_get_contents($this->project . '/AGENTS.md'));
        self::assertSame([], $sync->execute($this->project, true));
        file_put_contents($package . '/resources/ai/strictphp-conventions-sdk/SKILL.md', "---\nname: strictphp-conventions-sdk\ndescription: SDK guidance\n---\nUpdated SDK guidance");
        self::assertContains($target, $sync->execute($this->project, true));
        $sync->execute($this->project);
        self::assertSame("---\nname: strictphp-conventions-sdk\ndescription: SDK guidance\n---\nUpdated SDK guidance", file_get_contents($this->project . '/' . $target));
        file_put_contents($this->project . '/vendor/composer/installed.json', '{"packages":[]}');
        $sync->execute($this->project);
        self::assertFileDoesNotExist($this->project . '/' . $target);
        self::assertStringNotContainsString('[strictphp-conventions-sdk]', (string) file_get_contents($this->project . '/AGENTS.md'));
    }

    public function testPackageSkillWithReferencesCanBeRemovedAndInstalledAgain(): void
    {
        $package = $this->registerPackage('vendor/sdk');
        mkdir($package . '/resources/ai/strictphp-conventions-sdk/references');
        file_put_contents($package . '/resources/ai/strictphp-conventions-sdk/references/body.md', 'Body guidance');
        $metadata = $this->project . '/vendor/composer/installed.json';
        $installed = file_get_contents($metadata);
        self::assertIsString($installed);
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        file_put_contents($metadata, '{"packages":[]}');
        $sync->execute($this->project);
        self::assertDirectoryDoesNotExist($this->project . '/.agents/skills/strictphp-conventions-sdk');
        file_put_contents($metadata, $installed);
        $sync->execute($this->project);
        self::assertSame('Body guidance', file_get_contents($this->project . '/.agents/skills/strictphp-conventions-sdk/references/body.md'));
        self::assertSame([], $sync->execute($this->project, true));
    }

    public function testRegisteredSkillCannotOverwriteLocalEdits(): void
    {
        $this->registerPackage('vendor/sdk');
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        file_put_contents($this->project . '/.agents/skills/strictphp-conventions-sdk/SKILL.md', 'Local edit');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Locally\ changed/');
        $sync->execute($this->project);
    }

    public function testPackagesCannotRegisterTheSameSkill(): void
    {
        $this->registerPackage('vendor/first');
        $this->registerPackage('vendor/second');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Duplicate\ package\ skill/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testPackageCannotReplaceABundledSkill(): void
    {
        $this->registerPackage('vendor/sdk');
        mkdir($this->resources . '/strictphp-conventions-sdk');
        file_put_contents($this->resources . '/strictphp-conventions-sdk/SKILL.md', "---\nname: strictphp-conventions-sdk\ndescription: Core guidance\n---\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Duplicate\ package\ skill/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testPackageCannotTakeOverAnUnmanagedSkill(): void
    {
        $this->registerPackage('vendor/sdk');
        mkdir($this->project . '/.agents/skills/strictphp-conventions-sdk', recursive: true);
        file_put_contents($this->project . '/.agents/skills/strictphp-conventions-sdk/local.md', 'My guidance');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not\ managed/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testSkillSourceCannotEscapeThePackageThroughASymlink(): void
    {
        $package = $this->registerPackage('vendor/sdk');
        unlink($package . '/resources/ai/strictphp-conventions-sdk/SKILL.md');
        symlink($this->resources . '/AGENTS.md', $package . '/resources/ai/strictphp-conventions-sdk/SKILL.md');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/symlinks/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testTraversalInRegisteredSourceIsRejected(): void
    {
        $this->registerPackage('vendor/sdk', '../outside');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid\ package\ skill\ name\ or\ source\ path/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testCustomVendorDirectoryAndLinkedPackageAreSupported(): void
    {
        $package = $this->registerPackage('vendor/sdk', vendor: 'dependencies');
        file_put_contents($this->project . '/composer.json', '{"config":{"vendor-dir":"dependencies"}}');
        rename($package, $this->directory . '/linked-sdk');
        symlink($this->directory . '/linked-sdk', $package);
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        self::assertSame("---\nname: strictphp-conventions-sdk\ndescription: SDK guidance\n---\nSDK guidance", file_get_contents($this->project . '/.agents/skills/strictphp-conventions-sdk/SKILL.md'));
        self::assertSame([], $sync->execute($this->project, true));
    }

    public function testRootProjectCanRegisterAnUnprefixedSkillWithoutInstalledMetadata(): void
    {
        mkdir($this->project . '/resources/ai/api-sdk-builder', recursive: true);
        file_put_contents($this->project . '/resources/ai/api-sdk-builder/SKILL.md', "---\nname: api-sdk-builder\ndescription: Build API clients.\n---\n");
        file_put_contents($this->project . '/composer.json', json_encode([
            'name' => 'example/project',
            'extra' => [
                'strictphp/conventions' => [
                    'skills' => [
                        'api-sdk-builder' => 'resources/ai/api-sdk-builder',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        self::assertFileExists($this->project . '/.agents/skills/api-sdk-builder/SKILL.md');
        $manifest = json_decode((string) file_get_contents($this->project . '/.agents/.strictphp-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertIsArray($manifest['files']);
        self::assertIsArray($manifest['files']['.agents/skills/api-sdk-builder/SKILL.md']);
        self::assertSame('example/project', $manifest['files']['.agents/skills/api-sdk-builder/SKILL.md']['package']);
        self::assertStringContainsString('Build API clients.', (string) file_get_contents($this->project . '/AGENTS.md'));
        self::assertStringNotContainsString('Larastrict', (string) file_get_contents($this->project . '/AGENTS.md'));
        self::assertSame([], $sync->execute($this->project, true));
    }

    public function testRootAndInstalledPackageCannotRegisterTheSameSkill(): void
    {
        $this->registerPackage('vendor/sdk');
        file_put_contents($this->project . '/composer.json', json_encode([
            'extra' => [
                'strictphp/conventions' => [
                    'skills' => [
                        'strictphp-conventions-sdk' => 'resources/ai/sdk',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Duplicate\ package\ skill/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testExistingSkillCannotChangePackageOwner(): void
    {
        $this->registerPackage('vendor/sdk');
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        $metadata = $this->project . '/vendor/composer/installed.json';
        $installed = json_decode((string) file_get_contents($metadata), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($installed);
        self::assertIsArray($installed['packages']);
        self::assertIsArray($installed['packages'][0]);
        $installed['packages'][0]['name'] = 'another/sdk';
        file_put_contents($metadata, json_encode($installed, JSON_THROW_ON_ERROR));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/ownership\ changed/');
        $sync->execute($this->project);
    }

    public function testMismatchedSkillNameStopsSyncBeforeWrites(): void
    {
        $package = $this->registerPackage('vendor/sdk');
        file_put_contents($package . '/resources/ai/strictphp-conventions-sdk/SKILL.md', "---\nname: wrong\ndescription: SDK guidance\n---\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/matching\ name/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testManifestSymlinkCannotRedirectWrites(): void
    {
        mkdir($this->project . '/.agents');
        file_put_contents($this->directory . '/outside', '{}');
        symlink($this->directory . '/outside', $this->project . '/.agents/.strictphp-manifest.json');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/regular\ local\ path/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testPackageInstructionsAreCheckedUpdatedAndRemoved(): void
    {
        $package = $this->registerInstructions('vendor/sdk', ['resources/ai/AGENTS.md']);
        file_put_contents($package . '/resources/ai/AGENTS.md', 'Preserve external field names.');
        $sync = new InstructionSync($this->resources);
        $before = file_get_contents($this->project . '/AGENTS.md');
        self::assertContains('AGENTS.md', $sync->execute($this->project, true));
        self::assertSame($before, file_get_contents($this->project . '/AGENTS.md'));
        $sync->execute($this->project);
        self::assertStringContainsString("## vendor/sdk\n\nPreserve external field names.", (string) file_get_contents($this->project . '/AGENTS.md'));
        self::assertFileDoesNotExist($this->project . '/.agents/skills/strictphp-conventions-sdk/SKILL.md');
        self::assertSame([], $sync->execute($this->project, true));

        file_put_contents($package . '/resources/ai/AGENTS.md', 'Updated SDK instructions.');
        self::assertContains('AGENTS.md', $sync->execute($this->project, true));
        $sync->execute($this->project);
        self::assertStringContainsString('Updated SDK instructions.', (string) file_get_contents($this->project . '/AGENTS.md'));
        file_put_contents($this->project . '/vendor/composer/installed.json', '{"packages":[]}');
        self::assertContains('AGENTS.md', $sync->execute($this->project, true));
        $sync->execute($this->project);
        self::assertStringNotContainsString('Updated SDK instructions.', (string) file_get_contents($this->project . '/AGENTS.md'));
        self::assertStringStartsWith("# Local rules\n\nKeep this text.\n", (string) file_get_contents($this->project . '/AGENTS.md'));
    }

    public function testRootInstructionsPreserveFileOrderAndLocalOverrides(): void
    {
        file_put_contents($this->project . '/first.md', 'First instruction.');
        file_put_contents($this->project . '/second.md', 'Second instruction.');
        file_put_contents($this->project . '/composer.json', '{"name":"example/project","extra":{"strictphp/conventions":{"instructions":["second.md","first.md"]}}}');
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        file_put_contents($this->project . '/AGENTS.md', "\nLocal override.\n", FILE_APPEND);
        self::assertSame([], $sync->execute($this->project, true));
        $agents = (string) file_get_contents($this->project . '/AGENTS.md');
        self::assertStringContainsString("## example/project\n\nSecond instruction.\n\nFirst instruction.", $agents);
        self::assertStringEndsWith("\nLocal override.\n", $agents);
    }

    public function testLinkedInstructionPackageUsesCustomVendorDirectory(): void
    {
        $package = $this->registerInstructions('vendor/sdk', ['resources/ai/AGENTS.md'], 'dependencies');
        file_put_contents($package . '/resources/ai/AGENTS.md', 'Linked package instructions.');
        file_put_contents($this->project . '/composer.json', '{"config":{"vendor-dir":"dependencies"}}');
        rename($package, $this->directory . '/linked-sdk');
        symlink($this->directory . '/linked-sdk', $package);
        (new InstructionSync($this->resources))->execute($this->project);
        self::assertStringContainsString('Linked package instructions.', (string) file_get_contents($this->project . '/AGENTS.md'));
    }

    public function testInstructionsCannotEscapeTheirPackage(): void
    {
        $this->registerInstructions('vendor/sdk', ['../outside.md']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid package instruction source path/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testInstructionSymlinkIsRejectedBeforeWrites(): void
    {
        $package = $this->registerInstructions('vendor/sdk', ['resources/ai/AGENTS.md']);
        symlink($this->project . '/AGENTS.md', $package . '/resources/ai/AGENTS.md');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/regular local path/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testInstructionsCannotInjectSyncMarkers(): void
    {
        $package = $this->registerInstructions('vendor/sdk', ['resources/ai/AGENTS.md']);
        file_put_contents($package . '/resources/ai/AGENTS.md', '<!-- strictphp:end -->');

        try {
            (new InstructionSync($this->resources))->execute($this->project);
            self::fail('Expected invalid instruction markers.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('sync markers', $exception->getMessage());
            self::assertDirectoryDoesNotExist($this->project . '/.agents');
        }
    }

    public function testMissingInstructionFileIsRejected(): void
    {
        $this->registerInstructions('vendor/sdk', ['resources/ai/missing.md']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/instruction source must stay inside/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testGeneratedAgentsFileCannotAlsoBeAnInstructionSource(): void
    {
        file_put_contents($this->project . '/composer.json', '{"extra":{"strictphp/conventions":{"instructions":["AGENTS.md"]}}}');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cannot also be an instruction source/');
        (new InstructionSync($this->resources))->execute($this->project);
    }

    public function testBundledInstructionsAreCustomizableAndRegisteredOnlyOnce(): void
    {
        file_put_contents($this->resources . '/AGENTS.md', 'Shared package baseline.');
        // A linked development install can expose the same baseline through metadata.
        mkdir($this->project . '/vendor/composer', recursive: true);
        file_put_contents($this->project . '/vendor/composer/installed.json', json_encode([
            'packages' => [[
                'name' => 'strictphp/conventions',
                'install-path' => '../../..',
                'extra' => [
                    'strictphp/conventions' => [
                        'instructions' => ['resources/AGENTS.md'],
                    ],
                ],
            ]],
        ], JSON_THROW_ON_ERROR));
        $sync = new InstructionSync($this->resources);
        $sync->execute($this->project);
        self::assertSame(1, substr_count((string) file_get_contents($this->project . '/AGENTS.md'), 'Shared package baseline.'));
        file_put_contents($this->resources . '/AGENTS.md', 'Updated package baseline.');
        self::assertContains('AGENTS.md', $sync->execute($this->project, true));
        $sync->execute($this->project);
        self::assertStringContainsString('Updated package baseline.', (string) file_get_contents($this->project . '/AGENTS.md'));
    }

    /**
     * @param list<string> $paths
     */
    private function registerInstructions(string $name, array $paths, string $vendor = 'vendor'): string
    {
        $package = $this->registerPackage($name, vendor: $vendor);
        file_put_contents($this->project . '/' . $vendor . '/composer/installed.json', json_encode([
            'packages' => [[
                'name' => $name,
                'install-path' => '../' . $name,
                'extra' => [
                    'strictphp/conventions' => [
                        'instructions' => $paths,
                    ],
                ],
            ]],
        ], JSON_THROW_ON_ERROR));

        return $package;
    }

    private function registerPackage(string $name, string $source = 'resources/ai/strictphp-conventions-sdk', string $vendor = 'vendor'): string
    {
        $package = $this->project . '/' . $vendor . '/' . $name;
        mkdir($package . '/resources/ai/strictphp-conventions-sdk', recursive: true);
        file_put_contents($package . '/resources/ai/strictphp-conventions-sdk/SKILL.md', "---\nname: strictphp-conventions-sdk\ndescription: SDK guidance\n---\nSDK guidance");
        $metadata = $this->project . '/' . $vendor . '/composer/installed.json';
        $packages = [];

        if (is_file($metadata)) {
            $existing = json_decode((string) file_get_contents($metadata), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($existing);
            $packages = $existing['packages'];
            self::assertIsArray($packages);
        } else {
            mkdir(dirname($metadata), recursive: true);
        }

        $packages[] = [
            'name' => $name,
            'install-path' => '../' . $name,
            'extra' => [
                'strictphp/conventions' => [
                    'skills' => [
                        'strictphp-conventions-sdk' => $source,
                    ],
                ],
            ],
        ];
        file_put_contents($metadata, json_encode([
            'packages' => $packages,
        ], JSON_THROW_ON_ERROR));

        return $package;
    }
}
