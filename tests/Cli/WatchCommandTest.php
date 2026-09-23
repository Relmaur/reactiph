<?php

declare(strict_types=1);

namespace Reactiph\Tests\Cli;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Reactiph\Cli\BuildResult;
use Reactiph\Cli\WatchCommand;

/**
 * Watches `Fixtures/Simple` directly rather than a copy elsewhere --
 * ComponentDiscovery only picks up a class that resolves through the real
 * PSR-4 autoloader (ADR 0020), so a fixture copied into an arbitrary temp
 * directory outside `tests/`'s own autoload mapping would never actually
 * be discovered. Touches (not edits) `Widget.php`'s mtime to simulate a
 * change -- safe to do to a real fixture file since content never changes.
 */
final class WatchCommandTest extends TestCase
{
    private const SOURCE_DIR = __DIR__ . '/Fixtures/Simple';
    private const WATCHED_FILE = self::SOURCE_DIR . '/Widget.php';

    private string $outputDir;
    private int $originalMtime;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/reactiph-watch-out-' . bin2hex(random_bytes(4));
        $this->originalMtime = (int) filemtime(self::WATCHED_FILE);
    }

    #[After]
    public function cleanUp(): void
    {
        touch(self::WATCHED_FILE, $this->originalMtime);

        if (is_dir($this->outputDir)) {
            foreach (glob($this->outputDir . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->outputDir);
        }
    }

    public function testBuildsOnceImmediatelyEvenWithNoChangeDetected(): void
    {
        /** @var list<BuildResult> $builds */
        $builds = [];

        (new WatchCommand())->watch(
            self::SOURCE_DIR,
            $this->outputDir,
            function (BuildResult $result) use (&$builds): void {
                $builds[] = $result;
            },
            intervalMs: 10,
            maxIterations: 0,
        );

        self::assertCount(1, $builds);
        self::assertFileExists($this->outputDir . '/Widget.js');
    }

    public function testRebuildsWhenAWatchedFileChanges(): void
    {
        /** @var list<BuildResult> $builds */
        $builds = [];

        $onBuild = function (BuildResult $result) use (&$builds): void {
            $builds[] = $result;

            if (count($builds) === 1) {
                // touch()'s mtime has one-second resolution -- force a
                // different second boundary than the baseline snapshot,
                // or the "change" could round-trip to an identical mtime.
                touch(self::WATCHED_FILE, $this->originalMtime + 1);
            }
        };

        (new WatchCommand())->watch(
            self::SOURCE_DIR,
            $this->outputDir,
            $onBuild,
            intervalMs: 50,
            maxIterations: 30,
        );

        self::assertGreaterThanOrEqual(2, count($builds));
    }

    public function testSnapshotOnlyTracksPhpAndTemplateFiles(): void
    {
        $noteFile = self::SOURCE_DIR . '/notes.txt';
        file_put_contents($noteFile, 'ignored');

        $snapshot = WatchCommand::snapshot(self::SOURCE_DIR);

        unlink($noteFile);

        self::assertArrayHasKey(self::WATCHED_FILE, $snapshot);
        self::assertArrayNotHasKey($noteFile, $snapshot);
    }
}
