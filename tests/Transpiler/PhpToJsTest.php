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

    public function testStringConcatenationCoercesBothSidesWithJsString(): void
    {
        $js = (new PhpToJs())->transpileMethod('public function g(): string { return $this->a . $this->b; }');

        self::assertSame("function g() {\nreturn (String(this.a) + String(this.b));\n}\n", $js);
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

    public function testRejectsPropertyWritesWithAHelpfulHint(): void
    {
        try {
            (new PhpToJs())->transpileMethod('public function set(): void { $this->a = 1; }');
            self::fail('Expected a TranspileException.');
        } catch (TranspileException $e) {
            self::assertSame('transpiler.property_write_unsupported', $e->code());
        }
    }

    public function testRejectsLoops(): void
    {
        $this->expectException(TranspileException::class);

        (new PhpToJs())->transpileMethod('public function loop(): void { for ($i = 0; $i < 10; $i++) {} }');
    }

    public function testRejectsMethodCalls(): void
    {
        $this->expectException(TranspileException::class);

        (new PhpToJs())->transpileMethod('public function call(): mixed { return $this->helper(); }');
    }

    public function testRejectsArrayLiterals(): void
    {
        $this->expectException(TranspileException::class);

        (new PhpToJs())->transpileMethod('public function arr(): array { return [1, 2, 3]; }');
    }

    public function testUnsupportedConstructExceptionCarriesLineNumber(): void
    {
        try {
            (new PhpToJs())->transpileMethod(
                "public function loop(): void {\n    for (\$i = 0; \$i < 10; \$i++) {}\n}",
            );
            self::fail('Expected a TranspileException.');
        } catch (TranspileException $e) {
            self::assertSame('transpiler.unsupported_construct', $e->code());
            self::assertArrayHasKey('line', $e->context());
        }
    }
}
