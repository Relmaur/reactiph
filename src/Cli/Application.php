<?php

declare(strict_types=1);

namespace Reactiph\Cli;

use Reactiph\Exception\ReactiphException;

/**
 * `bin/reactiph`'s real entry point — parses `$argv`, dispatches to
 * {@see CompileCommand}, {@see BuildCommand}, or {@see WatchCommand}, and
 * turns a thrown {@see ReactiphException} into a structured stderr message
 * plus a non-zero exit code rather than a raw stack trace, matching ADR
 * 0009's ethos applied to a CLI instead of a caught-and-rendered web
 * request. Deliberately hand-rolled argv parsing, not a dependency like
 * symfony/console — three subcommands, each taking one or two positional
 * directory/class arguments, don't need option parsing, help generation,
 * or anything else a console framework buys.
 *
 * Writes to injected output/error streams (default `STDOUT`/`STDERR`) so
 * tests can capture output without shelling out for every case — a real
 * process invocation (`bin/reactiph` itself) is still exercised separately
 * as a smoke test, since a resource stream substitution doesn't prove the
 * actual executable/shebang/autoload-bootstrapping works.
 */
final class Application
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout = STDOUT,
        private $stderr = STDERR,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;

        try {
            return match ($command) {
                'compile' => $this->runCompile($argv),
                'build' => $this->runBuild($argv),
                'watch' => $this->runWatch($argv),
                default => $this->printUsage(),
            };
        } catch (ReactiphException $e) {
            $this->writeError($e);

            return 1;
        }
    }

    /**
     * @param list<string> $argv
     */
    private function runCompile(array $argv): int
    {
        $componentClass = $argv[2] ?? null;

        if ($componentClass === null) {
            fwrite($this->stderr, "Usage: reactiph compile <ComponentClass>\n");

            return 2;
        }

        $js = (new CompileCommand())->run($componentClass);
        fwrite($this->stdout, $js);

        return 0;
    }

    /**
     * @param list<string> $argv
     */
    private function runBuild(array $argv): int
    {
        [$sourceDir, $outputDir] = $this->requireTwoPaths($argv, 'build <source-dir> <output-dir>');

        if ($sourceDir === null) {
            return 2;
        }

        $result = (new BuildCommand())->run($sourceDir, $outputDir);

        fwrite($this->stdout, sprintf(
            "Built %d component(s) -> %s\n",
            count($result->classes),
            $result->manifestPath,
        ));

        return 0;
    }

    /**
     * @param list<string> $argv
     */
    private function runWatch(array $argv): int
    {
        [$sourceDir, $outputDir] = $this->requireTwoPaths($argv, 'watch <source-dir> <output-dir>');

        if ($sourceDir === null) {
            return 2;
        }

        fwrite($this->stdout, sprintf("Watching %s for changes...\n", $sourceDir));

        (new WatchCommand())->watch($sourceDir, $outputDir, function (BuildResult $result): void {
            fwrite($this->stdout, sprintf(
                "[%s] rebuilt %d component(s)\n",
                date('H:i:s'),
                count($result->classes),
            ));
        });

        return 0;
    }

    /**
     * @param list<string> $argv
     * @return array{0: ?string, 1: string}
     */
    private function requireTwoPaths(array $argv, string $usage): array
    {
        $first = $argv[2] ?? null;
        $second = $argv[3] ?? null;

        if ($first === null || $second === null) {
            fwrite($this->stderr, "Usage: reactiph {$usage}\n");

            return [null, ''];
        }

        return [$first, $second];
    }

    private function printUsage(): int
    {
        fwrite($this->stderr, implode("\n", [
            'Usage:',
            '  reactiph compile <ComponentClass>       Compile one component, print its JS',
            '  reactiph build <source-dir> <output-dir> Transpile every component under a directory to static JS + a manifest',
            '  reactiph watch <source-dir> <output-dir> Rebuild whenever a component file changes',
            '',
        ]));

        return 2;
    }

    private function writeError(ReactiphException $e): void
    {
        fwrite($this->stderr, sprintf("Error [%s]: %s\n", $e->code(), $e->getMessage()));

        if ($e->hint() !== null) {
            fwrite($this->stderr, "Hint: {$e->hint()}\n");
        }
    }
}
