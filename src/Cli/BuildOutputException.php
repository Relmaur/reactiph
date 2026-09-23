<?php

declare(strict_types=1);

namespace Reactiph\Cli;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

/**
 * Thrown by {@see BuildCommand} when `$outputDir` can't be created or
 * written to — a real, not hypothetical, failure mode (permissions, a
 * path component that's actually a file, a read-only filesystem).
 */
final class BuildOutputException extends \RuntimeException implements ReactiphException
{
    use CarriesDiagnostics;

    public static function cannotCreateDirectory(string $outputDir): self
    {
        $e = new self(sprintf('Could not create output directory "%s".', $outputDir));

        return $e->withDiagnostics(
            'cli.build_output_uncreatable',
            ['output_dir' => $outputDir],
            'Check filesystem permissions, and that no path component of the output directory is an existing file.',
        );
    }
}
