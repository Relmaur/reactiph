<?php

declare(strict_types=1);

namespace Reactiph\Tests\Cli;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Reactiph\Cli\BuildCommand;
use Reactiph\Cli\DuplicateComponentNameException;
use Reactiph\Tests\Cli\Fixtures\Simple\Widget;

final class BuildCommandTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/reactiph-build-test-' . bin2hex(random_bytes(4));
    }

    #[After]
    public function cleanUpOutputDir(): void
    {
        if (!is_dir($this->outputDir)) {
            return;
        }

        foreach (glob($this->outputDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->outputDir);
    }

    public function testWritesOneJsFilePerDiscoveredComponentAndAManifest(): void
    {
        $result = (new BuildCommand())->run(__DIR__ . '/Fixtures/Simple', $this->outputDir);

        self::assertSame([Widget::class], $result->classes);
        self::assertFileExists($this->outputDir . '/Widget.js');
        self::assertSame($this->outputDir . '/manifest.json', $result->manifestPath);

        $js = file_get_contents($this->outputDir . '/Widget.js');
        self::assertStringContainsString('increment', $js);
    }

    public function testManifestMapsEachClassToItsFileAndAContentHash(): void
    {
        (new BuildCommand())->run(__DIR__ . '/Fixtures/Simple', $this->outputDir);

        $manifest = json_decode(
            (string) file_get_contents($this->outputDir . '/manifest.json'),
            true,
        );

        self::assertArrayHasKey(Widget::class, $manifest);
        self::assertSame('Widget.js', $manifest[Widget::class]['file']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $manifest[Widget::class]['hash']);
    }

    public function testCreatesTheOutputDirectoryIfItDoesNotExist(): void
    {
        self::assertDirectoryDoesNotExist($this->outputDir);

        (new BuildCommand())->run(__DIR__ . '/Fixtures/Simple', $this->outputDir);

        self::assertDirectoryExists($this->outputDir);
    }

    public function testThrowsWhenTwoDiscoveredComponentsShareAShortClassName(): void
    {
        $this->expectException(DuplicateComponentNameException::class);
        (new BuildCommand())->run(__DIR__ . '/Fixtures/Duplicate', $this->outputDir);
    }
}
