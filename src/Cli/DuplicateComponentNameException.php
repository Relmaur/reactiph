<?php

declare(strict_types=1);

namespace Reactiph\Cli;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

/**
 * Thrown by {@see BuildCommand} when two discovered components share a
 * short class name — `bin/reactiph build` names each output file after its
 * class's short name (`Counter.js`), the same naming ADR 0020's
 * `ComponentDiscovery` already uses for tag registration, so a real
 * collision here would otherwise silently overwrite one component's
 * output with another's.
 */
final class DuplicateComponentNameException extends \RuntimeException implements ReactiphException
{
    use CarriesDiagnostics;

    /**
     * @param class-string $first
     * @param class-string $second
     */
    public static function forShortName(string $shortName, string $first, string $second): self
    {
        $e = new self(sprintf(
            'Both %s and %s share the short name "%s" — their build output would collide at %s.js.',
            $first,
            $second,
            $shortName,
            $shortName,
        ));

        return $e->withDiagnostics(
            'cli.duplicate_component_name',
            ['short_name' => $shortName, 'first' => $first, 'second' => $second],
            'Rename one of the two classes, or register it manually with ComponentRegistry instead of relying on directory discovery.',
        );
    }
}
