<?php declare(strict_types=1);

namespace StrictPhp\Conventions\ECS;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * Keep control flow easy to read by expanding control bodies onto separate lines.
 * Add a blank line between a completed control structure and the next statement.
 */
final class BlankLineAfterControlStructureFixer extends AbstractFixer
{
    private const array CONTROL_TOKENS = [
        T_DECLARE,
        T_DO,
        T_ELSE,
        T_ELSEIF,
        T_FINALLY,
        T_FOR,
        T_FOREACH,
        T_IF,
        T_SWITCH,
        T_TRY,
        T_WHILE,
        T_CATCH,
    ];
    private const array CONDITIONAL_CONTROL_TOKENS = [
        T_DECLARE,
        T_ELSEIF,
        T_FOR,
        T_FOREACH,
        T_IF,
        T_SWITCH,
        T_WHILE,
        T_CATCH,
    ];

    public function getDefinition(): FixerDefinitionInterface {
        return new FixerDefinition(
            'A blank line must follow a completed control structure when another statement follows.',
            [new CodeSample("<?php\nif (ready()) {\n    start();\n}\nfinish();\n")],
        );
    }

    public function isCandidate(Tokens $tokens): bool {
        return true;
    }

    /**
     * Run after the control structure braces are present.
     */
    public function getPriority(): int {
        return -10;
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void {
        $this->expandControlBodies($tokens);

        $controlEnds = $this->findControlEnds($tokens);

        $controlEnds = array_unique($controlEnds);
        rsort($controlEnds);

        foreach ($controlEnds as $endIndex) {
            $next = $this->getNextNonCommentToken($tokens, $endIndex);

            if (null !== $next && $tokens[$next]->equals('}')) {
                $this->putParentCloseOnNewLine($tokens, $endIndex, $next);

                continue;
            }

            if (null === $next || $tokens[$next]->equals(';') || $tokens[$next]->isGivenKind(T_CLOSE_TAG)) {
                continue;
            }

            $whitespaceIndex = $endIndex + 1;

            if (! $tokens[$whitespaceIndex]->isWhitespace()) {
                $tokens->insertAt(
                    $whitespaceIndex,
                    new Token([T_WHITESPACE, "\n\n" . $this->getIndentationBeforeToken($tokens, $endIndex)]),
                );

                continue;
            }

            $whitespace = $tokens[$whitespaceIndex]->getContent();

            if (preg_match('/(?:\r\n|\n|\r).*(?:\r\n|\n|\r)/s', $whitespace) === 1) {
                continue;
            }

            if (preg_match('/\r\n|\n|\r/', $whitespace, $lineBreak) === 1) {
                $whitespace = preg_replace('/\r\n|\n|\r/', $lineBreak[0] . $lineBreak[0], $whitespace, 1) ?? $whitespace;
            } else {
                $whitespace = "\n\n" . $this->getIndentationBeforeToken($tokens, $endIndex);
            }

            $tokens[$whitespaceIndex] = new Token([T_WHITESPACE, $whitespace]);
        }
    }

    /**
     * @return list<array{control: int, start: int, end: int}>
     */
    private function findControlBodies(Tokens $tokens): array {
        $bodies = [];

        foreach ($tokens as $index => $token) {
            if (! $token->isGivenKind(self::CONTROL_TOKENS)) {
                continue;
            }

            if ($token->isGivenKind(T_ELSE)) {
                $next = $tokens->getNextMeaningfulToken($index);

                if (null !== $next && $tokens[$next]->isGivenKind(T_IF)) {
                    continue;
                }
            }

            $bodyStart = $this->findBodyStart($tokens, $index, $token);

            if (null === $bodyStart || ! $tokens[$bodyStart]->equals('{')) {
                continue;
            }

            $bodyEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_BRACE, $bodyStart);
            $bodies[] = [
                'control' => $index,
                'start' => $bodyStart,
                'end' => $bodyEnd,
            ];
        }

        return $bodies;
    }

    private function expandControlBodies(Tokens $tokens): void {
        $bodies = $this->findControlBodies($tokens);

        if ([] === $bodies) {
            return;
        }

        $source = $tokens->generateCode();
        $offsets = [];
        $offset = 0;
        foreach ($tokens as $index => $token) {
            $offsets[$index] = $offset;
            $offset += strlen($token->getContent());
        }

        preg_match('/\r\n|\n|\r/', $source, $lineBreakMatch);
        $lineBreak = $lineBreakMatch[0] ?? "\n";
        $operations = [];

        foreach ($bodies as $body) {
            $indent = $this->getLineIndent(
                $source,
                $offsets[$body['control']],
                $tokens,
                $offsets,
            );
            $bodyFirst = $body['start'] + 1;
            while ($bodyFirst < $body['end'] && $tokens[$bodyFirst]->isWhitespace()) {
                $bodyFirst++;
            }

            if ($bodyFirst === $body['end']) {
                $this->addWhitespaceOperation(
                    $operations,
                    $tokens,
                    $body['start'] + 1,
                    $lineBreak . $indent,
                );

                continue;
            }

            $this->addWhitespaceOperation(
                $operations,
                $tokens,
                $body['start'] + 1,
                $lineBreak . $indent . '    ',
            );
            $this->addWhitespaceOperation(
                $operations,
                $tokens,
                $body['end'] - 1,
                $lineBreak . $indent,
                $body['end'],
            );
            $this->separateBodyStatements($operations, $tokens, $body, $lineBreak, $indent . '    ');
        }

        krsort($operations);
        foreach ($operations as $index => $operation) {
            $whitespace = new Token([T_WHITESPACE, $operation['content']]);

            if ($operation['insert']) {
                $tokens->insertAt($index, $whitespace);
            } else {
                $tokens[$index] = $whitespace;
            }
        }
    }

    /**
     * @param array{control: int, start: int, end: int} $body
     * @param array<int, array{content: string, insert: bool}> $operations
     */
    private function separateBodyStatements(
        array &$operations,
        Tokens $tokens,
        array $body,
        string $lineBreak,
        string $indent,
    ): void {
        $braceDepth = 0;
        $parenthesisDepth = 0;

        for ($index = $body['start'] + 1; $index < $body['end']; $index++) {
            $token = $tokens[$index];

            if ($token->equals('{')) {
                $braceDepth++;

                continue;
            }

            if ($token->equals('}')) {
                $braceDepth--;

                continue;
            }

            if ($token->equals('(')) {
                $parenthesisDepth++;

                continue;
            }

            if ($token->equals(')')) {
                $parenthesisDepth--;

                continue;
            }

            if (! $token->equals(';') || 0 !== $braceDepth || 0 !== $parenthesisDepth) {
                continue;
            }

            $next = $tokens->getNextMeaningfulToken($index);

            if (null === $next || $next === $body['end'] || $tokens[$next]->isComment()) {
                continue;
            }

            $whitespace = $index + 1;

            if ($tokens[$whitespace]->isWhitespace()
                && preg_match('/(?:\r\n|\n|\r).*(?:\r\n|\n|\r)/s', $tokens[$whitespace]->getContent()) === 1) {
                continue;
            }

            $this->addWhitespaceOperation($operations, $tokens, $index + 1, $lineBreak . $indent);
        }
    }

    /**
     * @param array<int, array{content: string, insert: bool}> $operations
     */
    private function addWhitespaceOperation(
        array &$operations,
        Tokens $tokens,
        int $index,
        string $content,
        ?int $insertionIndex = null,
    ): void {
        if ($tokens[$index]->isWhitespace()) {
            $operations[$index] = [
                'content' => $content,
                'insert' => false,
            ];

            return;
        }

        $operations[$insertionIndex ?? $index] = [
            'content' => $content,
            'insert' => true,
        ];
    }

    /**
     * @return list<int>
     */
    private function findControlEnds(Tokens $tokens): array {
        $controlEnds = [];

        foreach ($this->findControlBodies($tokens) as $body) {
            $next = $this->getNextNonCommentToken($tokens, $body['end']);

            if (null !== $next && $this->isContinuation($tokens, $next, $tokens[$body['control']])) {
                if ($tokens[$body['control']]->isGivenKind(T_DO)) {
                    $controlEnds[] = $this->findDoWhileEnd($tokens, $next);
                }

                continue;
            }

            $controlEnds[] = $body['end'];
        }

        return $controlEnds;
    }

    /**
     * @param array<int, int> $offsets
     */
    private function getLineIndent(string $source, int $offset, Tokens $tokens, array $offsets): string {
        $prefix = substr($source, 0, $offset);
        $lineFeed = strrpos($prefix, "\n");
        $carriageReturn = strrpos($prefix, "\r");
        $lineStart = max(false === $lineFeed ? -1 : $lineFeed, false === $carriageReturn ? -1 : $carriageReturn) + 1;
        $linePrefix = substr($prefix, $lineStart);
        preg_match('/^[ \t]*/', $linePrefix, $matches);

        $indent = $matches[0] ?? '';
        $inlineBlockDepth = 0;
        foreach ($tokens as $index => $token) {
            if ($offsets[$index] < $lineStart) {
                continue;
            }

            if ($offsets[$index] >= $offset) {
                break;
            }

            if ($token->equals('{')) {
                $inlineBlockDepth++;
            } elseif ($token->equals('}')) {
                $inlineBlockDepth = max(0, $inlineBlockDepth - 1);
            }
        }

        return $indent . str_repeat('    ', $inlineBlockDepth);
    }

    private function putParentCloseOnNewLine(Tokens $tokens, int $controlEnd, int $parentClose): void {
        $whitespaceIndex = $controlEnd + 1;

        if ($tokens[$whitespaceIndex]->isWhitespace()
            && preg_match('/\r\n|\n|\r/', $tokens[$whitespaceIndex]->getContent()) === 1) {
            return;
        }

        $source = $tokens->generateCode();
        $offsets = [];
        $offset = 0;
        foreach ($tokens as $index => $token) {
            $offsets[$index] = $offset;
            $offset += strlen($token->getContent());
        }

        $parentOpen = $tokens->findBlockStart(Tokens::BLOCK_TYPE_BRACE, $parentClose);
        $indent = $this->getLineIndent($source, $offsets[$parentOpen], $tokens, $offsets);
        preg_match('/\r\n|\n|\r/', $source, $lineBreakMatch);
        $lineBreak = $lineBreakMatch[0] ?? "\n";

        if ($tokens[$whitespaceIndex]->isWhitespace()) {
            $tokens[$whitespaceIndex] = new Token([T_WHITESPACE, $lineBreak . $indent]);

            return;
        }

        $tokens->insertAt($whitespaceIndex, new Token([T_WHITESPACE, $lineBreak . $indent]));
    }

    private function findBodyStart(Tokens $tokens, int $index, Token $controlToken): ?int {
        $next = $tokens->getNextMeaningfulToken($index);

        if ($controlToken->isGivenKind(self::CONDITIONAL_CONTROL_TOKENS)) {
            if (null === $next || ! $tokens[$next]->equals('(')) {
                return null;
            }

            $conditionEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $next);

            return $tokens->getNextMeaningfulToken($conditionEnd);
        }

        return $next;
    }

    private function isContinuation(Tokens $tokens, int $index, Token $controlToken): bool {
        if ($tokens[$index]->isGivenKind(T_WHILE)) {
            return $controlToken->isGivenKind(T_DO);
        }

        return $tokens[$index]->isGivenKind([T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY]);
    }

    private function findDoWhileEnd(Tokens $tokens, int $whileIndex): int {
        $conditionStart = $tokens->getNextMeaningfulToken($whileIndex);

        if (null === $conditionStart || ! $tokens[$conditionStart]->equals('(')) {
            return $whileIndex;
        }

        $conditionEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $conditionStart);
        $statementEnd = $tokens->getNextMeaningfulToken($conditionEnd);

        return null !== $statementEnd && $tokens[$statementEnd]->equals(';')
            ? $statementEnd
            : $conditionEnd;
    }

    private function getNextNonCommentToken(Tokens $tokens, int $index): ?int {
        $next = $tokens->getNextMeaningfulToken($index);

        while (null !== $next && $tokens[$next]->isComment()) {
            $next = $tokens->getNextMeaningfulToken($next);
        }

        return $next;
    }

    private function getIndentationBeforeToken(Tokens $tokens, int $index): string {
        $previous = $index - 1;

        if (! $tokens[$previous]->isWhitespace()) {
            return '';
        }

        preg_match('/(?:\r\n|\n|\r)([ \t]*)$/', $tokens[$previous]->getContent(), $matches);

        return $matches[1] ?? '';
    }
}
