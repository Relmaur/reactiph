<?php

declare(strict_types=1);

namespace Reactiph\Cli;

use Reactiph\Component\ComponentDiscovery;
use Reactiph\Transpiler\ComponentTranspiler;

/**
 * `bin/reactiph build <source-dir> <output-dir>` — discovers every
 * component under `$sourceDir` (the same static-parse-then-autoload scan
 * {@see ComponentDiscovery} uses for ADR 0020) and transpiles each into its
 * own static `<ShortClassName>.js` file under `$outputDir`, plus a
 * `manifest.json` mapping each component's fully-qualified class name to
 * its output file and a content hash.
 *
 * This is the concrete piece ADR 0021 deferred: dynamically-transpiled
 * component JS (`ComponentTranspiler::transpileComponent()` called at
 * request time) has no static file a build pipeline like Vite can bundle.
 * A pre-built manifest gives any host bridge — `wordpress-bridge`,
 * `taw-bridge`, a future one — something real to point at instead, without
 * this package needing to know anything about that host's own asset
 * pipeline. Consuming the manifest (e.g. wiring `taw-bridge` to prefer a
 * pre-built file over transpiling at request time) is intentionally not
 * done here — real, separate scope per host.
 */
final class BuildCommand
{
    private readonly ComponentTranspiler $transpiler;

    public function __construct(?ComponentTranspiler $transpiler = null)
    {
        $this->transpiler = $transpiler ?? new ComponentTranspiler();
    }

    public function run(string $sourceDir, string $outputDir): BuildResult
    {
        $classes = ComponentDiscovery::classesInDirectory($sourceDir);

        if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            throw BuildOutputException::cannotCreateDirectory($outputDir);
        }

        /** @var array<class-string, array{file: string, hash: string}> $manifest */
        $manifest = [];

        /** @var array<string, class-string> $seenShortNames */
        $seenShortNames = [];

        foreach ($classes as $class) {
            $shortName = (new \ReflectionClass($class))->getShortName();

            if (isset($seenShortNames[$shortName])) {
                throw DuplicateComponentNameException::forShortName($shortName, $seenShortNames[$shortName], $class);
            }

            $seenShortNames[$shortName] = $class;

            $js = $this->transpiler->transpileComponent($class);
            $filename = $shortName . '.js';

            file_put_contents(rtrim($outputDir, '/') . '/' . $filename, $js);

            $manifest[$class] = [
                'file' => $filename,
                'hash' => substr(sha1($js), 0, 12),
            ];
        }

        $manifestPath = rtrim($outputDir, '/') . '/manifest.json';
        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        return new BuildResult($classes, $manifestPath);
    }
}
