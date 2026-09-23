<?php

declare(strict_types=1);

namespace Reactiph\Transpiler;

/**
 * Extracts a method's exact PHP source text via Reflection, for feeding
 * to {@see PhpToJs::transpileMethod()}. Used by {@see ComponentTranspiler}
 * to assemble a component's client-side method definitions, and by the
 * parity test suite (`tests/Transpiler/ParityTest.php`) to guarantee the
 * PHP fed to the transpiler is exactly what real PHP executes — never a
 * separately hand-typed copy that could silently drift out of sync with
 * it.
 */
final class MethodSourceReader
{
    public static function read(\ReflectionMethod $method): string
    {
        $filename = $method->getFileName();

        if ($filename === false) {
            throw TranspileException::couldNotReadMethodSource(
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            );
        }

        $lines = file($filename);

        if ($lines === false) {
            throw TranspileException::couldNotReadMethodSource(
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            );
        }

        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
