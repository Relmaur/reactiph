<?php

declare(strict_types=1);

namespace Reactiph\Tests\Transpiler;

use PHPUnit\Framework\TestCase;
use Reactiph\Transpiler\PhpToJs;
use Reactiph\Transpiler\TranspileException;

/**
 * Fast, Node-free unit tests for PhpToJs's output shape and its allow-list
 * enforcement. The parity suite (ParityTest) is what actually proves
 * behavioral correctness against real PHP — these tests are about the
 * transpiler's own contract: what it emits, and what it refuses to.
 */
final class PhpToJsTest extends TestCase
{
    public function testTranspilesAPropertyReturnExactly(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function count(): int { return $this->count; }');

        self::assertSame("function count() {\nreturn this.count;\n}\n", $js);
    }

    public function testTranspilesArithmeticExactly(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function sum(): int { return $this->a + $this->b; }');

        self::assertSame("function sum() {\nreturn (this.a + this.b);\n}\n", $js);
    }

    public function testHoistsLocalVariablesAssignedInsideABranchToTheTopOfTheFunction(): void
    {
        $js = (new PhpToJs())->transpileMethod(
            'public function pick(): string { '
            . 'if ($this->a) { $x = "big"; } else { $x = "small"; } return $x; }',
        );

        self::assertSame(
            "function pick() {\nlet x;\nif (__phpBool(this.a)) {\nx = \"big\";\n} else {\nx = \"small\";\n}\nreturn x;\n}\n",
            $js,
        );
    }

    public function testIfConditionAndBooleanOperandsAreWrappedInPhpTruthiness(): void
    {
        $js = (new PhpToJs())->transpileMethod(
            'public function ok(): bool { if ($this->a && $this->b) { return true; } return false; }',
        );

        self::assertStringContainsString('if (__phpBool((__phpBool(this.a) && __phpBool(this.b))))', $js);
    }

    public function testStringConcatenationUsesPhpStringifyRules(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function g(): string { return $this->a . $this->b; }');

        self::assertSame(
            "function g() {\nreturn (__phpString(this.a) + __phpString(this.b));\n}\n",
            $js,
        );
    }

    public function testStrictComparisonUsesTripleEquals(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function eq(): bool { return $this->a === $this->b; }');

        self::assertStringContainsString('(this.a === this.b)', $js);
    }

    public function testRejectsLooseEqualityWithAHelpfulHint(): void
    {
        try {
            (new PhpToJs())->transpileMethod('public function eq(): bool { return $this->a == $this->b; }');
            self::fail('Expected a TranspileException.');
        } catch (TranspileException $e) {
            self::assertSame('transpiler.loose_comparison_unsupported', $e->code());
            self::assertStringContainsString('===', (string) $e->hint());
        }
    }

    public function testRejectsLooseInequality(): void
    {
        $this->expectException(TranspileException::class);

        (new PhpToJs())->transpileMethod('public function ne(): bool { return $this->a != $this->b; }');
    }

    public function testTranspilesPropertyWrites(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function set(): void { $this->a = 1; }');

        self::assertSame("function set() {\nthis.a = 1;\n}\n", $js);
    }

    public function testTranspilesMethodCallsWithPositionalArguments(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function call(): mixed { return $this->helper($this->a); }');

        self::assertSame("function call() {\nreturn this.helper(this.a);\n}\n", $js);
    }

    public function testRejectsMethodCallsWithNamedArguments(): void
    {
        $this->expectException(TranspileException::class);

        (new PhpToJs())->transpileMethod('public function call(): mixed { return $this->helper(x: 1); }');
    }

    public function testTranspilesPreAndPostIncrementDecrement(): void
    {
        $js = (new PhpToJs())->transpileMethod(
            'public function all(): void { $i = 0; $i++; ++$i; $i--; --$i; }',
        );

        self::assertStringContainsString('i++;', $js);
        self::assertStringContainsString('++i;', $js);
        self::assertStringContainsString('i--;', $js);
        self::assertStringContainsString('--i;', $js);
    }

    public function testTranspilesAWhileLoop(): void
    {
        $js = (new PhpToJs())->transpileMethod(
            'public function loop(): void { $i = 0; while ($i < 3) { $i++; } }',
        );

        self::assertStringContainsString('while (__phpBool((i < 3))) {', $js);
    }

    public function testTranspilesAForLoopAndHoistsItsCounter(): void
    {
        $js = (new PhpToJs())->transpileMethod(
            'public function loop(): void { for ($i = 0; $i < 3; $i++) { $this->a = $i; } }',
        );

        self::assertStringContainsString('let i;', $js);
        self::assertStringContainsString('for (i = 0; __phpBool((i < 3)); i++) {', $js);
    }

    public function testTranspilesAListArrayLiteral(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function arr(): array { return [1, 2, 3]; }');

        self::assertSame("function arr() {\nreturn [1, 2, 3];\n}\n", $js);
    }

    public function testTranspilesAnAssociativeArrayLiteral(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function arr(): array { return ["a" => 1, "b" => 2]; }');

        self::assertSame("function arr() {\nreturn {\"a\": 1, \"b\": 2};\n}\n", $js);
    }

    public function testTranspilesArrayReadAccess(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function first(): mixed { return $this->items[0]; }');

        self::assertSame("function first() {\nreturn this.items[0];\n}\n", $js);
    }

    public function testTranspilesArrayWriteAccessWithAnExplicitKey(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function set(): void { $this->items[0] = "x"; }');

        self::assertSame("function set() {\nthis.items[0] = \"x\";\n}\n", $js);
    }

    public function testRejectsArrayAppendSyntax(): void
    {
        try {
            (new PhpToJs())->transpileMethod('public function push(): void { $this->items[] = "x"; }');
            self::fail('Expected a TranspileException.');
        } catch (TranspileException $e) {
            self::assertSame('transpiler.array_append_unsupported', $e->code());
        }
    }

    public function testRejectsMixedKeyArrayLiterals(): void
    {
        try {
            (new PhpToJs())->transpileMethod('public function arr(): array { return [0 => "a", "x" => "b"]; }');
            self::fail('Expected a TranspileException.');
        } catch (TranspileException $e) {
            self::assertSame('transpiler.unsupported_array_shape', $e->code());
        }
    }

    public function testRejectsGappedIntegerKeyArrayLiterals(): void
    {
        $this->expectException(TranspileException::class);

        (new PhpToJs())->transpileMethod('public function arr(): array { return [0 => "a", 2 => "b"]; }');
    }

    public function testTranspilesForeachOverAValueOnly(): void
    {
        $js = (new PhpToJs())->transpileMethod(
            'public function sum(): int { $total = 0; foreach ($this->items as $item) { $total = $total + $item; } return $total; }',
        );

        self::assertStringContainsString('for (const [__k, __v] of __phpEntries(this.items)) {', $js);
        self::assertStringContainsString('item = __v;', $js);
        self::assertStringContainsString('let total, item;', $js);
    }

    public function testTranspilesForeachWithKeyAndValue(): void
    {
        $js = (new PhpToJs())->transpileMethod(
            'public function keys(): array { $out = []; foreach ($this->items as $k => $v) { $out[$k] = $v; } return $out; }',
        );

        self::assertStringContainsString('k = __k;', $js);
        self::assertStringContainsString('v = __v;', $js);
    }

    public function testTranspilesAllowlistedStdlibFunctions(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function len(): int { return strlen($this->name); }');

        self::assertSame("function len() {\nreturn __phpStrlen(this.name);\n}\n", $js);
    }

    public function testRejectsFunctionsNotOnTheStdlibAllowlist(): void
    {
        try {
            (new PhpToJs())->transpileMethod('public function m(): array { return array_map($f, $this->items); }');
            self::fail('Expected a TranspileException.');
        } catch (TranspileException $e) {
            self::assertSame('transpiler.unsupported_stdlib_function', $e->code());
            self::assertSame('array_map', $e->context()['function']);
        }
    }

    public function testRejectsInArrayWithoutExplicitStrictTrue(): void
    {
        try {
            (new PhpToJs())->transpileMethod(
                'public function has(): bool { return in_array($this->a, $this->items); }',
            );
            self::fail('Expected a TranspileException.');
        } catch (TranspileException $e) {
            self::assertSame('transpiler.loose_in_array_unsupported', $e->code());
        }
    }

    public function testRejectsInArrayWithStrictFalse(): void
    {
        $this->expectException(TranspileException::class);

        (new PhpToJs())->transpileMethod(
            'public function has(): bool { return in_array($this->a, $this->items, false); }',
        );
    }

    public function testTranspilesInArrayWithExplicitStrictTrue(): void
    {
        $js = (new PhpToJs())->transpileMethod(
            'public function has(): bool { return in_array($this->a, $this->items, true); }',
        );

        self::assertStringContainsString('__phpInArray(this.a, this.items)', $js);
    }

    public function testUnsupportedConstructExceptionCarriesLineNumber(): void
    {
        try {
            (new PhpToJs())->transpileMethod(
                "public function pick(): string {\n    switch (\$this->a) { default: return 'x'; }\n}",
            );
            self::fail('Expected a TranspileException.');
        } catch (TranspileException $e) {
            self::assertSame('transpiler.unsupported_construct', $e->code());
            self::assertArrayHasKey('line', $e->context());
        }
    }
}
