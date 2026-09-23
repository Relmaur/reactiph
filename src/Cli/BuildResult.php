<?php

declare(strict_types=1);

namespace Reactiph\Cli;

use Reactiph\Component\BaseComponent;

/**
 * What {@see BuildCommand::run()} actually did — data, not printed output,
 * so `Application` (real stdout) and tests (assertions) can both consume
 * the same result.
 */
final class BuildResult
{
    /**
     * @param list<class-string<BaseComponent>> $classes
     */
    public function __construct(
        public readonly array $classes,
        public readonly string $manifestPath,
    ) {
    }
}
