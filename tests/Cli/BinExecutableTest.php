<?php

declare(strict_types=1);

namespace Reactiph\Tests\Cli;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

/**
 * Shells out to the real `bin/reactiph` executable rather than calling
 * `Application` in-process — proves the shebang, argv wiring, and
 * autoload-bootstrapping actually work as a real process invocation, the
 * same reasoning Part 4's Node parity tests and Part 6's `php -S` curl
 * checks already apply to anything crossing a process boundary. A resource
 * stream substitution in `ApplicationTest` doesn't exercise any of that.
 */
final class BinExecutableTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/reactiph-bin-test-' . bin2hex(random_bytes(4));
    }

    #[After]
    public function cleanUp(): void
    {
        if (is_dir($this->outputDir)) {
            foreach (glob($this->outputDir . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->outputDir);
        }
    }

    public function testCompileAgainstTheRealFolderBasedExample(): void
    {
        $result = $this->runBin(['compile', 'ReactiphExamples\\Greeting']);

        self::assertSame(0, $result['exitCode']);
        self::assertStringContainsString("window.ReactiphComponents['ReactiphExamples\\\\Greeting']", $result['stdout']);
    }

    public function testBuildWritesRealFilesToDisk(): void
    {
        $result = $this->runBin(['build', $this->repoPath('examples/folder-based'), $this->outputDir]);

        self::assertSame(0, $result['exitCode']);
        self::assertFileExists($this->outputDir . '/Greeting.js');
        self::assertFileExists($this->outputDir . '/manifest.json');
    }

    public function testUnknownCommandExitsWithUsage(): void
    {
        $result = $this->runBin(['nonsense']);

        self::assertSame(2, $result['exitCode']);
        self::assertStringContainsString('Usage', $result['stderr']);
    }

    /**
     * @param list<string> $args
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runBin(array $args): array
    {
        $command = array_merge(['php', $this->repoPath('bin/reactiph')], $args);

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->repoPath(''),
        );

        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return ['exitCode' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function repoPath(string $relative): string
    {
        return dirname(__DIR__, 2) . '/' . $relative;
    }
}
