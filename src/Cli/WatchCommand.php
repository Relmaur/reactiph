<?php

declare(strict_types=1);

namespace Reactiph\Cli;

/**
 * `bin/reactiph watch <source-dir> <output-dir>` — reruns
 * {@see BuildCommand} once immediately, then again every time a `.php` or
 * `.reactiph.html` file under `$sourceDir` changes.
 *
 * Polls file mtimes rather than a native filesystem-events extension
 * (`inotify`, `fswatch`, ...) — those aren't installed by default and
 * differ per OS; a plain mtime scan needs nothing beyond core PHP and
 * behaves identically everywhere this framework already runs. The
 * tradeoff (a real change can take up to `$intervalMs` to be noticed) is
 * a fine one for a local dev loop, not something this build tool needs to
 * optimize away.
 */
final class WatchCommand
{
    public function __construct(private readonly BuildCommand $build = new BuildCommand())
    {
    }

    /**
     * @param callable(BuildResult): void $onBuild called once for the
     *   initial build, then again after every rebuild triggered by a
     *   detected change.
     * @param int|null $maxIterations caps the number of polls — real CLI
     *   usage never passes this (the loop runs until the process is
     *   killed); tests use it so the loop actually returns.
     */
    public function watch(
        string $sourceDir,
        string $outputDir,
        callable $onBuild,
        int $intervalMs = 500,
        ?int $maxIterations = null,
    ): void {
        // Snapshotted before the initial build runs, not after -- a build
        // only ever writes to $outputDir, never $sourceDir, but taking the
        // baseline first is the more correct ordering regardless: nothing
        // that happens *during* the initial build should be silently
        // absorbed into "no change yet".
        $mtimes = self::snapshot($sourceDir);
        $onBuild($this->build->run($sourceDir, $outputDir));

        $iteration = 0;

        while ($maxIterations === null || $iteration < $maxIterations) {
            usleep($intervalMs * 1000);

            $current = self::snapshot($sourceDir);

            if ($current !== $mtimes) {
                $mtimes = $current;
                $onBuild($this->build->run($sourceDir, $outputDir));
            }

            ++$iteration;
        }
    }

    /**
     * @return array<string, int> file path => mtime
     */
    public static function snapshot(string $sourceDir): array
    {
        if (!is_dir($sourceDir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
        );

        $mtimes = [];

        foreach ($iterator as $file) {
            if (!self::isWatchedFile($file->getPathname())) {
                continue;
            }

            $mtime = $file->getMTime();

            if ($mtime !== false) {
                $mtimes[$file->getPathname()] = $mtime;
            }
        }

        return $mtimes;
    }

    private static function isWatchedFile(string $path): bool
    {
        return str_ends_with($path, '.php') || str_ends_with($path, '.reactiph.html');
    }
}
