<?php

declare(strict_types=1);

namespace Reactiph\Tests\Transpiler\Support;

/**
 * Test-only infrastructure for the parity suite (ADR 0006) and for
 * ComponentTranspilerTest: executes transpiled JS in real Node. Not part
 * of the framework itself; nothing under src/ depends on this.
 */
final class NodeRunner
{
    /**
     * Calls a single transpiled function (as `PhpToJs::transpileMethod()`
     * produces it) with `this` bound to $context, and returns its JSON-
     * decoded return value — for comparing against what the equivalent
     * real PHP method returns.
     *
     * @param array<string, mixed> $context
     * @param array<string, string> $supportingMethods Other transpiled
     *   methods (name => JS source) attached onto the same `this` context
     *   before calling $functionName — needed when the method under test
     *   calls another method on $this.
     */
    public static function call(
        string $jsFunctionSource,
        string $functionName,
        array $context,
        array $supportingMethods = [],
    ): mixed {
        $contextJson = json_encode($context, JSON_THROW_ON_ERROR);

        $script = self::readRuntimeJs() . "\n" . $jsFunctionSource . "\n";

        foreach ($supportingMethods as $source) {
            $script .= $source . "\n";
        }

        $script .= "const __context = {$contextJson};\n";
        $script .= "__context.{$functionName} = {$functionName};\n";

        foreach (array_keys($supportingMethods) as $name) {
            $script .= "__context.{$name} = {$name};\n";
        }

        $script .= "const __result = __context.{$functionName}();\n";
        $script .= "process.stdout.write(JSON.stringify(__result === undefined ? null : __result));\n";

        return self::run($script, $functionName);
    }

    /**
     * Calls a method registered by {@see \Reactiph\Transpiler\ComponentTranspiler}
     * onto `window.ReactiphComponents[$componentClass].methods`, with
     * `this` bound to $context, and returns $context's state *after* the
     * call — for verifying a mutating method (e.g. `increment()`, which
     * returns nothing but writes `$this->count`) actually mutated what a
     * real component instance would have.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function callRegisteredComponentMethod(
        string $definitionJs,
        string $componentClass,
        string $methodName,
        array $context,
    ): array {
        $contextJson = json_encode($context, JSON_THROW_ON_ERROR);
        $componentClassJson = json_encode($componentClass, JSON_THROW_ON_ERROR);
        $methodNameJson = json_encode($methodName, JSON_THROW_ON_ERROR);

        // The assembled definition targets a browser's `window` global;
        // plain Node has no such global, so it's polyfilled for the test.
        $script = 'globalThis.window = globalThis.window || globalThis;' . "\n"
            . self::readRuntimeJs() . "\n"
            . $definitionJs . "\n"
            . "const __context = {$contextJson};\n"
            . "window.ReactiphComponents[{$componentClassJson}].methods[{$methodNameJson}].call(__context);\n"
            . "process.stdout.write(JSON.stringify(__context));\n";

        $result = self::run($script, $methodName);

        if (!is_array($result)) {
            throw new \RuntimeException("Expected an object back from Node for {$methodName}(), got: " . get_debug_type($result));
        }

        return $result;
    }

    private static function readRuntimeJs(): string
    {
        $runtimeJs = file_get_contents(dirname(__DIR__, 3) . '/packages/runtime-js/php-runtime.js');

        if ($runtimeJs === false) {
            throw new \RuntimeException('Could not read packages/runtime-js/php-runtime.js.');
        }

        return $runtimeJs;
    }

    private static function run(string $script, string $label): mixed
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'reactiph-parity-') . '.js';
        file_put_contents($tmpFile, $script);

        try {
            $output = shell_exec('node ' . escapeshellarg($tmpFile) . ' 2>&1');
        } finally {
            unlink($tmpFile);
        }

        if ($output === null || $output === '') {
            throw new \RuntimeException("Node produced no output for {$label}(). Script:\n{$script}");
        }

        try {
            return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                "Node output for {$label}() wasn't valid JSON — likely a JS runtime error:\n{$output}",
                previous: $e,
            );
        }
    }
}
