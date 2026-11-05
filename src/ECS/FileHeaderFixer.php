<?php declare(strict_types=1);

namespace StrictPhp\Conventions\ECS;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * Give PHP files a consistent header with strict types on the first line.
 * Preserve class PHPDoc and remove separate file PHPDoc from files with classes.
 * Format file PHPDoc across multiple lines in files without classes.
 */
final class FileHeaderFixer extends AbstractFixer
{
    public function getDefinition(): FixerDefinitionInterface {
        return new FixerDefinition(
            'Put strict types on line one and keep multiline file PHPDoc only in files without a class.',
            [new CodeSample("<?php\n/** File description. */\nclass Example {}\n")],
        );
    }

    public function isCandidate(Tokens $tokens): bool {
        return $tokens[0]->isGivenKind(T_OPEN_TAG);
    }

    public function isRisky(): bool {
        return true;
    }

    public function getPriority(): int {
        return -100;
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void {
        // Reuse PHP CS Fixer's declaration handling, including missing declarations.
        (new DeclareStrictTypesFixer())->fix($file, $tokens);
        $tokens->clearEmptyTokens();
        $declare = $tokens->getNextTokenOfKind(0, [[T_DECLARE]]);

        if (null === $declare) {
            return;
        }

        $end = $tokens->getNextTokenOfKind($declare, [';']);

        if (null === $end) {
            return;
        }

        $tokens->clearRange($declare, $end);

        if (isset($tokens[$end + 1]) && $tokens[$end + 1]->isWhitespace()) {
            $tokens->clearAt($end + 1);
        }

        $tokens->clearEmptyTokens();
        $tokens[0] = new Token([T_OPEN_TAG, '<?php ']);

        if (isset($tokens[1]) && $tokens[1]->isWhitespace()) {
            $tokens->clearAt(1);
        }

        $declaration = Tokens::fromCode('<?php declare(strict_types=1);');
        $declaration->clearAt(0);
        $declaration->clearEmptyTokens();

        $tokens->insertAt(1, $declaration);
        $tokens->clearEmptyTokens();

        $semicolon = $tokens->getNextTokenOfKind(0, [';']);

        if (null === $semicolon) {
            return;
        }

        if (! isset($tokens[$semicolon + 1])) {
            $tokens->insertAt($semicolon + 1, new Token([T_WHITESPACE, "\n"]));

            return;
        }

        $tokens->ensureWhitespaceAtIndex($semicolon + 1, 0, "\n\n");

        $header = $tokens->getNextNonWhitespace($semicolon);

        $classPhpdoc = $this->findClassPhpdoc($tokens);

        if (null !== $classPhpdoc) {
            while (null !== $header && $tokens[$header]->isGivenKind(T_COMMENT)
                && str_starts_with($tokens[$header]->getContent(), '//')) {
                $next = $tokens->getNextNonWhitespace($header);
                $this->removeHeader($tokens, $header);
                $header = $next;
            }
        }

        if (null === $header || $header === $classPhpdoc || ! $tokens[$header]->isGivenKind(T_DOC_COMMENT)) {
            return;
        }

        if ($tokens->isTokenKindFound(T_CLASS)) {
            $this->removeHeader($tokens, $header);

            return;
        }

        $content = $tokens[$header]->getContent();

        if (! str_contains($content, "\n") && ! str_contains($content, "\r")) {
            $text = trim(substr($content, 3, -2));
            $tokens[$header] = new Token([T_DOC_COMMENT, "/**\n * " . $text . "\n */"]);
        }
    }

    private function removeHeader(Tokens $tokens, int $header): void {
        $tokens->clearAt($header);

        if (isset($tokens[$header + 1]) && $tokens[$header + 1]->isWhitespace()) {
            $tokens->clearAt($header + 1);
        }
    }

    private function findClassPhpdoc(Tokens $tokens): ?int {
        foreach ($tokens as $index => $token) {
            if (! $token->isGivenKind(T_CLASS)) {
                continue;
            }

            $name = $tokens->getNextMeaningfulToken($index);

            if (null === $name || ! $tokens[$name]->isGivenKind(T_STRING)) {
                continue;
            }

            $previous = $tokens->getPrevNonWhitespace($index);

            while (null !== $previous) {
                if ($tokens[$previous]->isGivenKind(CT::T_ATTRIBUTE_CLOSE)) {
                    $previous = $tokens->findBlockStart(Tokens::BLOCK_TYPE_ATTRIBUTE, $previous);
                } elseif (! $tokens[$previous]->isGivenKind([T_FINAL, T_ABSTRACT, T_READONLY])) {
                    break;
                }

                $previous = $tokens->getPrevNonWhitespace($previous);
            }

            if (null !== $previous && $tokens[$previous]->isGivenKind(T_DOC_COMMENT)) {
                return $previous;
            }
        }

        return null;
    }
}
