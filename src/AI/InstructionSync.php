<?php declare(strict_types=1);

namespace StrictPhp\Conventions\AI;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class InstructionSync
{
    private const string Start = '<!-- strictphp:begin -->';
    private const string End = '<!-- strictphp:end -->';
    private const string Manifest = '.agents/.strictphp-manifest.json';

    public function __construct(
        private string $resources = __DIR__ . '/../../resources/ai',
    ) {
    }

    /**
     * @return list<string> Changed paths, also returned without writes in check mode.
     */
    public function execute(string $project, bool $check = false): array {
        $project = realpath($project) ?: throw new RuntimeException('Project directory does not exist.');

        if (is_file($project . '/composer.json') === false) {
            throw new RuntimeException('Run in a Composer project or use --project PATH.');
        }

        $files = [];
        $entries = [];
        $skillDirectories = [];
        $assets = $this->registeredAssets($project);
        $sources = $assets['skills'];

        if (! is_file($this->resources . '/strictphp/SKILL.md')) {
            throw new RuntimeException('The package must contain the StrictPHP skill.');
        }

        foreach (scandir($this->resources) ?: [] as $name) {
            if (! is_dir($this->resources . '/' . $name) || in_array($name, ['.', '..'], true)) {
                continue;
            }

            if (! self::isSkillName($name)) {
                throw new RuntimeException('Invalid package skill name: ' . $name);
            }

            if (isset($sources[$name])) {
                throw new RuntimeException('Duplicate package skill: ' . $name);
            }

            $sources[$name] = [
                'source' => $this->resources . '/' . $name,
                'package' => 'strictphp/conventions',
            ];
        }

        ksort($sources);
        $guidance = self::instructionText($this->resources . '/AGENTS.md');

        foreach ($sources as $name => $registration) {
            $source = $registration['source'];

            if (is_link($source) || is_link($source . '/SKILL.md')) {
                throw new RuntimeException('Skill source must not contain symlinks: ' . $source);
            }

            if (! is_file($source . '/SKILL.md')) {
                throw new RuntimeException('Missing SKILL.md for ' . $name);
            }

            self::validateSkill(self::read($source . '/SKILL.md'), $name);
            $directory = '.agents/skills/' . $name . '/';
            $skillDirectories[] = $directory;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isLink()) {
                    throw new RuntimeException('Skill source must not contain symlinks: ' . $file->getPathname());
                }

                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $path = $directory . substr($file->getPathname(), strlen($source . '/'));
                    $files[$path] = self::read($file->getPathname());
                    $entries[$path] = [
                        'package' => $registration['package'],
                        'hash' => hash('sha256', $files[$path]),
                    ];
                }
            }
        }

        foreach ($assets['instructions'] as $paths) {
            $contents = [];

            foreach ($paths as $source) {
                if (realpath($source) !== realpath($this->resources . '/AGENTS.md')) {
                    $contents[] = self::instructionText($source);
                }
            }

            if ($contents !== []) {
                $guidance .= "\n\n" . implode("\n\n", $contents);
            }
        }

        ksort($entries);
        $previous = $this->previousFiles($project);

        foreach ($previous as $path => $entry) {
            $this->assertLocalPath($project, $path);
            $absolute = $project . '/' . $path;

            if (isset($entries[$path]) && $entry['package'] !== $entries[$path]['package']) {
                throw new RuntimeException('Generated skill ownership changed: ' . $path);
            }

            if (is_file($absolute) && hash('sha256', self::read($absolute)) !== $entry['hash'] && self::read($absolute) !== ($files[$path] ?? null)) {
                throw new RuntimeException('Locally changed generated file: ' . $path . '. Move local rules to AGENTS.md before sync.');
            }

            if (array_key_exists($path, $files) === false) {
                $files[$path] = null;
            }
        }

        foreach ($skillDirectories as $directory) {
            $this->assertLocalPath($project, rtrim($directory, '/'));
            $wasManaged = false;

            foreach (array_keys($previous) as $oldPath) {
                if (str_starts_with($oldPath, $directory)) {
                    $wasManaged = true;

                    break;
                }
            }

            if ($wasManaged === false && is_dir($project . '/' . $directory) && scandir($project . '/' . $directory) !== ['.', '..']) {
                throw new RuntimeException('The generated skill directory is not managed: ' . $directory . '. Move it before sync.');
            }
        }

        $this->assertLocalPath($project, 'AGENTS.md');
        $agents = is_file($project . '/AGENTS.md') ? self::read($project . '/AGENTS.md') : '';
        $block = self::Start . "\n" . $guidance . "\n" . self::End;
        $files['AGENTS.md'] = self::replaceBlock($agents, self::Start, self::End, $block, true);
        $files[self::Manifest] = json_encode([
            'version' => 1,
            'files' => $entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        // Check every target before the first write. Never follow a project symlink.
        $changes = [];

        foreach ($files as $path => $contents) {
            $this->assertLocalPath($project, $path);
            $absolute = $project . '/' . $path;

            if (is_dir($absolute)) {
                throw new RuntimeException('Expected an instruction file, found a directory: ' . $path);
            }

            if (self::isManagedSkillPath($path) && $path !== self::Manifest && array_key_exists($path, $previous) === false && is_file($absolute) && self::read($absolute) !== $contents) {
                throw new RuntimeException('Unmanaged file conflicts with package instructions: ' . $path);
            }

            if ($contents === null ? is_file($absolute) : (is_file($absolute) === false || self::read($absolute) !== $contents)) {
                $changes[] = $path;
            }
        }

        if ($check) {
            return $changes;
        }

        foreach ($changes as $path) {
            $absolute = $project . '/' . $path;
            $contents = $files[$path];

            if ($contents === null) {
                if (unlink($absolute) === false) {
                    throw new RuntimeException('Cannot remove ' . $path);
                }

                $directory = dirname($absolute);
                $skillsRoot = $project . '/.agents/skills';

                while (str_starts_with($directory, $skillsRoot . '/') && scandir($directory) === ['.', '..']) {
                    if (rmdir($directory) === false) {
                        throw new RuntimeException('Cannot remove empty skill directory: ' . $directory);
                    }

                    $directory = dirname($directory);
                }

                continue;
            }

            if (is_dir(dirname($absolute)) === false && mkdir(dirname($absolute), 0755, true) === false) {
                throw new RuntimeException('Cannot create directory for ' . $path);
            }

            if (file_put_contents($absolute, $contents) === false) {
                throw new RuntimeException('Cannot write ' . $path);
            }
        }

        return $changes;
    }

    /**
     * @return array{skills: array<string, array{source: string, package: string}>, instructions: array<string, list<string>>}
     */
    private function registeredAssets(string $project): array
    {
        $composer = json_decode(self::read($project . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($composer)) {
            throw new RuntimeException('Invalid Composer project metadata.');
        }

        $config = $composer['config'] ?? [];

        if (! is_array($config)) {
            throw new RuntimeException('Invalid Composer configuration.');
        }

        $vendor = $config['vendor-dir'] ?? 'vendor';

        if (! is_string($vendor) || $vendor === '') {
            throw new RuntimeException('Invalid Composer vendor directory.');
        }

        $vendor = str_starts_with($vendor, '/') ? $vendor : $project . '/' . $vendor;
        $installed = $vendor . '/composer/installed.json';
        $packages = [];

        if (is_file($installed)) {
            $metadata = json_decode(self::read($installed), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($metadata)) {
                throw new RuntimeException('Invalid Composer installed package metadata.');
            }

            $packages = $metadata['packages'] ?? $metadata;
        }

        if (! is_array($packages)) {
            throw new RuntimeException('Invalid Composer installed package list.');
        }

        // Composer does not include the root package in installed.json.
        $packages[] = $composer;
        $skills = [];
        $instructions = [];

        foreach ($packages as $index => $package) {
            if (! is_array($package)) {
                throw new RuntimeException('Invalid Composer installed package.');
            }

            $extra = $package['extra'] ?? [];

            if (! is_array($extra)) {
                throw new RuntimeException('Invalid Composer package extra metadata.');
            }

            $conventions = $extra['strictphp/conventions'] ?? [];

            if (! is_array($conventions)) {
                throw new RuntimeException('Invalid package conventions metadata.');
            }

            $registrations = $conventions['skills'] ?? [];

            if (! is_array($registrations)) {
                throw new RuntimeException('Invalid package skill registrations.');
            }

            $instructionPaths = $conventions['instructions'] ?? [];

            if (! is_array($instructionPaths) || ! array_is_list($instructionPaths)) {
                throw new RuntimeException('Invalid package instruction registrations.');
            }

            if ($registrations === [] && $instructionPaths === []) {
                continue;
            }

            $owner = $package['name'] ?? 'root-project';

            if (! is_string($owner) || $owner === '') {
                throw new RuntimeException('Invalid Composer package name.');
            }

            if ($index === array_key_last($packages)) {
                $root = $project;
            } else {
                $installPath = $package['install-path'] ?? null;

                if (! is_string($installPath)) {
                    throw new RuntimeException('Missing Composer package install-path.');
                }

                $root = realpath(dirname($installed) . '/' . $installPath);
            }

            foreach ($instructionPaths as $relative) {
                if (! is_string($relative) || ! self::isRelativeSourcePath($relative)) {
                    throw new RuntimeException('Invalid package instruction source path.');
                }

                if ($root === false) {
                    throw new RuntimeException('Package instruction source must stay inside its package.');
                }

                $this->assertLocalPath($root, $relative);
                $source = realpath($root . '/' . $relative);

                if ($source === false || ! str_starts_with($source, $root . '/') || ! is_file($source)) {
                    throw new RuntimeException('Package instruction source must stay inside its package.');
                }

                if ($source === $project . '/AGENTS.md') {
                    throw new RuntimeException('The generated AGENTS.md cannot also be an instruction source.');
                }

                $instructions[$owner][] = $source;
            }

            foreach ($registrations as $name => $relative) {
                if (! is_string($name) || ! self::isSkillName($name) || ! is_string($relative) || ! self::isRelativeSourcePath($relative)) {
                    throw new RuntimeException('Invalid package skill name or source path.');
                }

                if (isset($skills[$name])) {
                    throw new RuntimeException('Duplicate package skill: ' . $name);
                }

                $source = $root === false ? false : realpath($root . '/' . $relative);

                if ($source === false || ! str_starts_with($source, $root . '/') || ! is_dir($source)) {
                    throw new RuntimeException('Package skill source must stay inside its package: ' . $name);
                }

                $skills[$name] = [
                    'source' => $source,
                    'package' => $owner,
                ];
            }
        }

        ksort($skills);
        ksort($instructions);

        return [
            'skills' => $skills,
            'instructions' => $instructions,
        ];
    }

    /**
     * @return array<string, array{package: string, hash: string}>
     */
    private function previousFiles(string $project): array
    {
        $this->assertLocalPath($project, self::Manifest);

        if (! is_file($project . '/' . self::Manifest)) {
            return [];
        }

        $data = json_decode(self::read($project . '/' . self::Manifest), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data) || ($data['version'] ?? null) !== 1 || ! is_array($data['files'] ?? null)) {
            throw new RuntimeException('Invalid StrictPHP instruction manifest.');
        }

        $previous = [];

        foreach ($data['files'] as $path => $entry) {
            if (! is_string($path) || ! self::isManagedSkillPath($path) || ! is_array($entry)
                || ! is_string($entry['hash'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $entry['hash']) !== 1
                || ! is_string($entry['package'] ?? null) || $entry['package'] === '') {
                throw new RuntimeException('Invalid StrictPHP instruction manifest entry.');
            }

            $previous[$path] = [
                'package' => $entry['package'],
                'hash' => $entry['hash'],
            ];
        }

        return $previous;
    }

    private static function isRelativeSourcePath(string $path): bool
    {
        return preg_match('~^(?!/)(?!.*(?:^|/)(?:\\.|\\.\\.)(?:/|$))[a-zA-Z0-9_./-]+$~D', $path) === 1;
    }

    private static function instructionText(string $path): string
    {
        if (is_link($path)) {
            throw new RuntimeException('Instruction sources must not be symlinks: ' . $path);
        }

        $contents = trim(self::read($path));

        if (str_contains($contents, self::Start) || str_contains($contents, self::End)) {
            throw new RuntimeException('Instruction sources must not contain sync markers: ' . $path);
        }

        return $contents;
    }

    private static function isSkillName(string $name): bool
    {
        return strlen($name) <= 64 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $name) === 1;
    }

    private static function isManagedSkillPath(string $path): bool
    {
        return preg_match('~^\\.agents/skills/[a-z0-9]+(?:-[a-z0-9]+)*/.+$~D', $path) === 1;
    }

    private static function validateSkill(string $contents, string $name): void
    {
        if (preg_match('/\\A---\\R(.*?)\\R---(?:\\R|$)/s', $contents, $frontmatter) !== 1
            || preg_match('/^name: *([^\\r\\n]+)$/m', $frontmatter[1], $skillName) !== 1
            || trim($skillName[1], " \"'") !== $name
            || preg_match('/^description: *([^\\r\\n]+)$/m', $frontmatter[1], $description) !== 1) {
            throw new RuntimeException('Skill must declare its matching name and a single-line description: ' . $name);
        }

        $value = trim($description[1], " \"'");

        if ($value === '' || in_array($value, ['|', '>', '|-', '>-', '|+', '>+'], true)) {
            throw new RuntimeException('Skill must declare a single-line description: ' . $name);
        }
    }

    private static function replaceBlock(string $agents, string $startMarker, string $endMarker, string $block, bool $append = false): string
    {
        $start = strpos($agents, $startMarker);
        $end = strpos($agents, $endMarker);

        if (substr_count($agents, $startMarker) !== substr_count($agents, $endMarker) || substr_count($agents, $startMarker) > 1 || ($start !== false && $end !== false && $end < $start)) {
            throw new RuntimeException('Invalid convention markers in AGENTS.md. Repair them before sync.');
        }

        if ($start !== false && $end !== false) {
            return substr($agents, 0, $start) . $block . substr($agents, $end + strlen($endMarker));
        }

        return $append ? $agents . ($agents === '' ? '' : (str_ends_with($agents, "\n\n") ? '' : (str_ends_with($agents, "\n") ? "\n" : "\n\n"))) . $block . "\n" : $agents;
    }

    private function assertLocalPath(string $project, string $path): void {
        $current = $project;

        foreach (explode('/', $path) as $part) {
            if (in_array($part, ['', '.', '..'], true) || str_contains($part, '\\')) {
                throw new RuntimeException('Invalid instruction path: ' . $path);
            }

            $current .= '/' . $part;

            if (is_link($current) || (file_exists($current) && is_file($current) === false && is_dir($current) === false)) {
                throw new RuntimeException('Instruction path must be a regular local path: ' . $path);
            }
        }
    }

    private static function read(string $path): string {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Cannot read ' . $path);
        }

        return $contents;
    }
}
