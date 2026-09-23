<?php

declare(strict_types=1);

namespace Reactiph\Runtime;

/**
 * Everything a client-side hydration script needs for one hydration root:
 * where to find it in the DOM (`id`, matching a `data-reactiph-id`
 * attribute), what component rendered it (`component`, informational — a
 * hand-written stub like Part 3's keys its own logic off this; a future
 * transpiler-driven runtime would use it to look up transpiled component
 * code), and its initial state.
 */
final class HydrationPayload
{
    /**
     * @param class-string $component
     * @param array<string, mixed> $state
     */
    public function __construct(
        public readonly string $id,
        public readonly string $component,
        public readonly array $state,
    ) {
    }
}
