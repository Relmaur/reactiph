<?php

declare(strict_types=1);

namespace Reactiph\Tests\Transpiler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reactiph\Tests\Transpiler\Fixtures\ParityFixture;
use Reactiph\Tests\Transpiler\Support\NodeRunner;
use Reactiph\Transpiler\PhpToJs;

/**
 * The parity suite ADR 0006 requires: for every supported construct, run
 * the exact same PHP method through real PHP and through
 * transpiled-JS-in-Node, and assert identical output. The method source
 * fed to the transpiler is read directly out of ParityFixture via
 * Reflection (see methodSource()) — never duplicated as a separate string
 * literal — so there's no way for the "PHP" and "JS" sides of a case to
 * silently drift apart from each other.
 */
final class ParityTest extends TestCase
{
    /**
     * @param array<string, mixed> $properties
     * @param string[] $supportingMethods Other fixture methods the method
     *   under test calls on $this — also transpiled and attached onto the
     *   same JS context, so the call resolves at runtime.
     */
    #[DataProvider('cases')]
    public function testTranspiledJsMatchesRealPhp(
        string $method,
        array $properties,
        array $supportingMethods = [],
    ): void {
        $fixture = new ParityFixture();

        foreach ($properties as $name => $value) {
            $fixture->{$name} = $value;
        }

        // Captured before calling the method: some methods under test
        // (e.g. incrementA) mutate the fixture's own properties, and the
        // JS side must start from the same pre-call state PHP did, not
        // whatever's left over afterward.
        $initialState = get_object_vars($fixture);

        $phpResult = $fixture->{$method}();

        $transpiler = new PhpToJs();
        $js = $transpiler->transpileMethod(self::methodSource(ParityFixture::class, $method));

        $supportingJs = [];
        foreach ($supportingMethods as $supportingMethod) {
            $supportingJs[$supportingMethod] = $transpiler->transpileMethod(
                self::methodSource(ParityFixture::class, $supportingMethod),
            );
        }

        $jsResult = NodeRunner::call($js, $method, $initialState, $supportingJs);

        self::assertSame($phpResult, $jsResult, "PHP vs transpiled-JS mismatch for {$method}()");
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>, 2?: string[]}>
     */
    public static function cases(): iterable
    {
        yield 'integer addition' => ['sum', ['a' => 3, 'b' => 4]];
        yield 'negative addition' => ['sum', ['a' => -5, 'b' => 2]];
        yield 'division producing a float' => ['divide', ['a' => 7, 'b' => 2]];
        yield 'string concatenation' => ['greeting', ['name' => 'Reactiph']];

        // PHP casts true to "1" and false to "" when stringified — not
        // "true"/"false" like JS's native String() does. Caught while
        // implementing stdlib builtins; fixed via __phpString() (ADR
        // 0014). These two cases would fail against the old raw String()
        // codegen.
        yield 'string concatenation: true stringifies to "1"' => ['activeLabel', ['active' => true]];
        yield 'string concatenation: false stringifies to ""' => ['activeLabel', ['active' => false]];
        yield 'strict equality: equal' => ['isEqual', ['a' => 5, 'b' => 5]];
        yield 'strict equality: not equal' => ['isEqual', ['a' => 5, 'b' => 6]];
        yield 'relational comparison' => ['isGreater', ['a' => 10, 'b' => 3]];

        // The single most important cases in this suite: PHP's truthiness
        // rules treat the *string* "0" as falsy, but JS's native
        // truthiness treats it as truthy (only "" is falsy for strings in
        // JS). If PhpToJs relied on JS's native && / || / ! instead of the
        // __phpBool() shim, these two would fail.
        yield 'truthiness: PHP "0" string is falsy (diverges from JS native truthiness)' =>
            ['truthyAnd', ['flag' => '0', 'name' => 'x']];
        yield 'truthiness: non-empty non-"0" string is truthy' =>
            ['truthyAnd', ['flag' => '1', 'name' => 'x']];
        yield 'boolean not on a falsy "0" string' => ['negated', ['flag' => '0']];
        yield 'boolean not on a truthy string' => ['negated', ['flag' => 'yes']];

        yield 'if/elseif/else: big branch' => ['branching', ['a' => 50]];
        yield 'if/elseif/else: small branch' => ['branching', ['a' => 5]];
        yield 'if/elseif/else: non-positive branch' => ['branching', ['a' => -1]];

        yield 'property write then read' => ['incrementA', ['a' => 4]];

        yield 'method call: $this->method() resolves on the same object' =>
            ['quadruple', ['a' => 3], ['double']];

        yield 'while loop accumulating a sum' => ['sumUpTo', ['a' => 5]];
        yield 'while loop: zero iterations' => ['sumUpTo', ['a' => 0]];

        yield 'for loop with a decrementing counter' => ['countDownSteps', ['a' => 4]];
        yield 'for loop: zero iterations' => ['countDownSteps', ['a' => 0]];

        yield 'sequential array literal (JS Array)' => ['listLiteral', []];
        yield 'string-keyed array literal (JS Object)' => ['assocLiteral', []];
        yield 'array read access' => ['firstItem', ['items' => [10, 20, 30]]];
        yield 'array write access with an explicit key' => ['setFirstItem', ['items' => [1, 2, 3]]];
        yield 'foreach over a sequential array (value only)' => ['sumItems', ['items' => [1, 2, 3, 4]]];
        yield 'foreach over an empty array' => ['sumItems', ['items' => []]];
        yield 'foreach with key and value over an associative array' =>
            ['joinLabels', ['labels' => ['a' => 'Apple', 'b' => 'Banana']]];

        yield 'count() on a list array' => ['itemCount', ['items' => [1, 2, 3]]];
        yield 'count() on an associative array' => ['labelCount', ['labels' => ['a' => 1, 'b' => 2]]];

        // strlen() counts bytes, not characters — "café" is 4 characters
        // but 5 bytes in UTF-8 (é is 2 bytes). JS's native .length would
        // give 4 (UTF-16 code units); __phpStrlen (TextEncoder-based)
        // must match PHP's 5.
        yield 'strlen() on a multi-byte UTF-8 string' => ['nameLength', ['name' => 'café']];
        yield 'strlen() on a plain ASCII string' => ['nameLength', ['name' => 'hello']];

        yield 'in_array() with strict:true, found' => ['hasItem', ['a' => 2, 'items' => [1, 2, 3]]];
        yield 'in_array() with strict:true, not found' => ['hasItem', ['a' => 9, 'items' => [1, 2, 3]]];

        yield 'array_key_exists(): key present' => ['hasLabelKey', ['flag' => 'a', 'labels' => ['a' => 1]]];
        yield 'array_key_exists(): key absent' => ['hasLabelKey', ['flag' => 'z', 'labels' => ['a' => 1]]];

        yield 'implode() over a list array' => ['implodedItems', ['items' => [1, 2, 3]]];
        yield 'explode() splits into a list array' => ['explodedName', ['name' => 'a,b,c']];

        // PHP's default trim() charset is a fixed ASCII list; JS's native
        // .trim() also strips U+00A0 (non-breaking space), which PHP's
        // default does not. __phpTrim must leave it untouched, matching
        // PHP, not strip it like native .trim() would.
        yield 'trim() leaves a non-breaking space untouched (PHP default charset)' =>
            ['trimmedName', ['name' => "\u{00A0}Hello\u{00A0}"]];
        yield 'trim() strips ordinary ASCII whitespace' => ['trimmedName', ['name' => "  Hello  \n"]];

        // PHP's strtolower()/strtoupper() (no locale set) are ASCII-only;
        // JS's native toLowerCase()/toUpperCase() are Unicode-aware and
        // would also transform "É", which PHP's default leaves untouched.
        yield 'strtolower() leaves non-ASCII characters untouched' => ['lowerName', ['name' => 'ÀBC']];
        yield 'strtoupper() leaves non-ASCII characters untouched' => ['upperName', ['name' => 'àbc']];

        yield 'str_replace() with scalar search/replace/subject' =>
            ['replacedName', ['flag' => 'l', 'name' => 'hello']];
    }

    /**
     * @param class-string $class
     */
    private static function methodSource(string $class, string $method): string
    {
        $reflection = new \ReflectionMethod($class, $method);
        $filename = $reflection->getFileName();

        if ($filename === false) {
            throw new \RuntimeException("Could not locate source file for {$class}::{$method}().");
        }

        $lines = file($filename);

        if ($lines === false) {
            throw new \RuntimeException("Could not read {$filename}.");
        }

        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
