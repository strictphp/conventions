<?php declare(strict_types=1);

namespace StrictPhp\ConventionsTests\ECS;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class FileHeaderFormattingTest extends TestCase
{
    #[DataProvider('headers')]
    public function testSharedFormatter(string $input, bool $hasHeader): void {
        $path = tempnam(sys_get_temp_dir(), 'ecs-header-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, $input);
            $root = dirname(__DIR__, 2);
            $process = $this->ecsProcess($root, $path);
            $process->mustRun();
            $formatted = file_get_contents($path);
            self::assertIsString($formatted);
            self::assertStringStartsWith("<?php declare(strict_types=1);\n", $formatted);
            self::assertSame($hasHeader, str_contains($formatted, '* File description.'));

            if ($hasHeader) {
                self::assertStringContainsString("/**\n * File description.\n */", $formatted);
            }

            if (str_contains($input, '@var')) {
                self::assertStringContainsString('@var list<string>', $formatted);
            }

            $process->mustRun();
            self::assertSame($formatted, file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    #[DataProvider('lineHeaders')]
    public function testLineHeaderRequiresDocumentedClass(string $input, bool $remove): void {
        $path = tempnam(sys_get_temp_dir(), 'ecs-line-header-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, $input);
            $root = dirname(__DIR__, 2);
            $process = $this->ecsProcess($root, $path);
            $process->mustRun();
            $formatted = file_get_contents($path);
            self::assertIsString($formatted);
            self::assertSame(! $remove, str_contains($formatted, '// File description.'));
            self::assertSame(! $remove, str_contains($formatted, '// More description.'));
            self::assertSame(str_contains($input, 'Class purpose.'), str_contains($formatted, 'Class purpose.'));
            self::assertSame(str_contains($input, '// Implementation detail.'), str_contains($formatted, '// Implementation detail.'));
            $process->mustRun();
            self::assertSame($formatted, file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    private function ecsProcess(string $root, string $path): Process {
        return new Process([
            $root . '/vendor/bin/ecs', 'check', '--fix',
            '--config=' . $root . '/extension-ecs.php', '--paths=' . $path,
        ], $root);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function lineHeaders(): iterable {
        $header = "<?php\n// File description.\n// More description.\n";
        $doc = "/**\n * Class purpose.\n */\n";
        yield 'namespaced documented class' => [$header . "namespace App;\n" . $doc . "final readonly class Example {}\n", true];
        yield 'global documented class' => [$header . $doc . "class Example {}\n", true];
        yield 'attributed class' => [$header . "namespace App;\n" . $doc . "#[Attribute]\nfinal class Example {}\n", true];
        yield 'undocumented class' => [$header . "namespace App;\nclass Example {}\n", false];
        yield 'documented method only' => [$header . "namespace App;\nclass Example {\n/** Method purpose. */\npublic function run(): void {}\n}\n", false];
        yield 'config' => [$header . "return [];\n", false];
        yield 'class reference' => [$header . "return Example::class;\n", false];
        yield 'implementation comments' => [$header . "namespace App;\n" . $doc . "class Example {\n// Implementation detail.\npublic function run(): void {}\n}\n", true];
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function headers(): iterable {
        yield 'declaration only' => ["<?php\n\ndeclare(strict_types=1);\n", false];
        yield 'empty file' => ["<?php\n", false];
        yield 'missing declaration' => ["<?php\n/** File description. */\nnamespace App;\nclass Example {}\n", false];
        yield 'existing declaration' => ["<?php\n/** File description. */\ndeclare (strict_types = 1);\nnamespace App;\nclass Example {}\n", false];
        yield 'config header' => ["<?php\n/** File description. */\nreturn ['key' => 'value'];\n", true];
        yield 'class reference' => ["<?php\n/** File description. */\nreturn Example::class;\n", true];
        yield 'member documentation' => ["<?php\n/** File description. */\nnamespace App;\nclass Example {\n/** @var list<string> */\npublic array \$items = [];\n}\n", false];
        yield 'no header' => ["<?php\ndeclare(strict_types=1);\nreturn [];\n", false];
    }
}
