<?php

declare(strict_types=1);

namespace Reactiph\Tests\Transpiler\Support;

/**
 * Test-only infrastructure for the parity suite (ADR 0006): executes a
 * transpiled JS function in real Node, with `this` bound to a given
 * context, and returns the JSON-decoded result — so a test can compare it
 * directly against what the equivalent real PHP method returns. Not part
 * of the framework itself; nothing under src/ depends on this.
 */
final class NodeRunner
{
    /**
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
        $runtimeJs = file_get_contents(dirname(__DIR__, 3) . '/packages/runtime-js/php-runtime.js');

        if ($runtimeJs === false) {
            throw new \RuntimeException('Could not read packages/runtime-js/php-runtime.js.');
        }

        $contextJson = json_encode($context, JSON_THROW_ON_ERROR);

        $script = $runtimeJs . "\n" . $jsFunctionSource . "\n";

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

        $tmpFile = tempnam(sys_get_temp_dir(), 'reactiph-parity-') . '.js';
        file_put_contents($tmpFile, $script);

        try {
            $output = shell_exec('node ' . escapeshellarg($tmpFile) . ' 2>&1');
        } finally {
            unlink($tmpFile);
        }

        if ($output === null || $output === '') {
            throw new \RuntimeException("Node produced no output for {$functionName}(). Script:\n{$script}");
        }

        try {
            return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                "Node output for {$functionName}() wasn't valid JSON — likely a JS runtime error:\n{$output}",
                previous: $e,
            );
        }
    }
}
