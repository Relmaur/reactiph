<?php

declare(strict_types=1);

namespace Reactiph\Transpiler;

use PhpParser\Node;
use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

/**
 * Thrown by {@see PhpToJs} for any PHP construct outside its documented,
 * explicit allow-list (ADR 0003) — a compile-time error, never
 * silently-wrong JS. Always built via a named constructor.
 */
final class TranspileException extends \RuntimeException implements ReactiphException
{
    use CarriesDiagnostics;

    public static function phpSyntaxError(string $parserMessage): self
    {
        $e = new self(sprintf('Could not parse method source: %s', $parserMessage));

        return $e->withDiagnostics('transpiler.php_syntax_error', ['parser_message' => $parserMessage]);
    }

    public static function expectedSingleMethod(): self
    {
        $e = new self('Expected exactly one method declaration.');

        return $e->withDiagnostics(
            'transpiler.expected_single_method',
            [],
            'PhpToJs::transpileMethod() takes the source of exactly one method (e.g. "public function foo(): int { ... }").',
        );
    }

    public static function unsupportedConstruct(Node $node): self
    {
        $e = new self(sprintf('Unsupported PHP construct for transpilation: %s.', $node->getType()));

        return $e->withDiagnostics(
            'transpiler.unsupported_construct',
            ['node_type' => $node->getType(), 'line' => $node->getStartLine()],
            'This construct is outside Reactiph\'s transpiled-PHP subset (see docs/adr/0003-*.md '
                . 'and docs/adr/0011-*.md). Rewrite the method to avoid it.',
        );
    }

    public static function looseComparisonNotSupported(Node $node): self
    {
        $e = new self(
            'Loose comparison (== or !=) is not supported for transpilation — PHP and JS loose-equality '
                . 'semantics diverge for some inputs.',
        );

        return $e->withDiagnostics(
            'transpiler.loose_comparison_unsupported',
            ['line' => $node->getStartLine()],
            'Use strict comparison (=== or !==) instead.',
        );
    }

    public static function propertyWriteNotSupported(Node $node): self
    {
        $e = new self('Writing to a property ($this->prop = ...) is not yet supported for transpilation.');

        return $e->withDiagnostics(
            'transpiler.property_write_unsupported',
            ['line' => $node->getStartLine()],
            'Only property reads are supported in this slice of the transpiler; property writes are planned '
                . 'for a later slice (see docs/STATUS.md).',
        );
    }
}
