<?php

declare(strict_types=1);

namespace Reactiph\Tests\Cli;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Reactiph\Cli\Application;
use Reactiph\Tests\Cli\Fixtures\Simple\Widget;

final class ApplicationTest extends TestCase
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    private string $outputDir;

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'r+');
        $this->stderr = fopen('php://memory', 'r+');
        $this->outputDir = sys_get_temp_dir() . '/reactiph-app-test-' . bin2hex(random_bytes(4));
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

    public function testCompilePrintsTheTranspiledJsToStdout(): void
    {
        $exitCode = (new Application($this->stdout, $this->stderr))->run(['reactiph', 'compile', Widget::class]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('window.ReactiphComponents', $this->readStream($this->stdout));
        self::assertSame('', $this->readStream($this->stderr));
    }

    public function testCompileWithNoClassPrintsUsageAndExitsTwo(): void
    {
        $exitCode = (new Application($this->stdout, $this->stderr))->run(['reactiph', 'compile']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage', $this->readStream($this->stderr));
    }

    public function testCompileOfAnUnknownClassExitsOneWithAStructuredError(): void
    {
        $exitCode = (new Application($this->stdout, $this->stderr))->run(['reactiph', 'compile', 'Nope\\NotReal']);

        self::assertSame(1, $exitCode);
        $error = $this->readStream($this->stderr);
        self::assertStringContainsString('cli.unknown_component_class', $error);
        self::assertStringContainsString('Hint:', $error);
    }

    public function testBuildReportsHowManyComponentsItBuilt(): void
    {
        $exitCode = (new Application($this->stdout, $this->stderr))->run([
            'reactiph', 'build', __DIR__ . '/Fixtures/Simple', $this->outputDir,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Built 1 component(s)', $this->readStream($this->stdout));
    }

    public function testNoCommandPrintsUsageAndExitsTwo(): void
    {
        $exitCode = (new Application($this->stdout, $this->stderr))->run(['reactiph']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage', $this->readStream($this->stderr));
    }

    /**
     * @param resource $stream
     */
    private function readStream($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
