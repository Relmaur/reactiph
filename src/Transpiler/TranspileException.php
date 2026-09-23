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

    public static function unsupportedArrayShape(Node $node): self
    {
        $e = new self(
            'Array literal must be either a plain sequential list ([1, 2, 3]) or a purely string-keyed '
                . 'associative array (["a" => 1]) — mixed, gapped, or computed keys are not supported.',
        );

        return $e->withDiagnostics(
            'transpiler.unsupported_array_shape',
            ['line' => $node->getStartLine()],
            'Rewrite as a plain sequential list or a purely string-keyed array.',
        );
    }

    public static function arrayAppendNotSupported(Node $node): self
    {
        $e = new self('Array append syntax ($arr[] = ...) is not supported for transpilation.');

        return $e->withDiagnostics(
            'transpiler.array_append_unsupported',
            ['line' => $node->getStartLine()],
            'Use an explicit key instead: $arr[$key] = ... .',
        );
    }

    public static function unsupportedStdlibFunction(Node $node, string $functionName): self
    {
        $e = new self(sprintf('%s() is not part of Reactiph\'s transpiled stdlib subset.', $functionName));

        return $e->withDiagnostics(
            'transpiler.unsupported_stdlib_function',
            ['function' => $functionName, 'line' => $node->getStartLine()],
            'See docs/adr/0015-*.md for the supported stdlib subset and why some common '
                . 'functions (array_map, array_filter, sprintf) aren\'t in it yet.',
        );
    }

    public static function looseInArrayNotSupported(Node $node): self
    {
        $e = new self(
            'in_array() without strict:true uses PHP loose comparison, which is not supported for transpilation.',
        );

        return $e->withDiagnostics(
            'transpiler.loose_in_array_unsupported',
            ['line' => $node->getStartLine()],
            'Pass true as the third argument: in_array($needle, $haystack, true).',
        );
    }

    public static function couldNotReadMethodSource(string $class, string $method): self
    {
        $e = new self(sprintf('Could not read source for %s::%s().', $class, $method));

        return $e->withDiagnostics(
            'transpiler.could_not_read_method_source',
            ['class' => $class, 'method' => $method],
        );
    }
}
