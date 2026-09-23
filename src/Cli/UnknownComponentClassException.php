<?php

declare(strict_types=1);

namespace Reactiph\Cli;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

/**
 * Thrown by {@see CompileCommand} when the given class name either doesn't
 * exist (via the ordinary autoloader — no static file parsing here, unlike
 * {@see \Reactiph\Component\ComponentDiscovery}, since the caller already
 * named one specific class) or isn't a `BaseComponent` subclass.
 */
final class UnknownComponentClassException extends \InvalidArgumentException implements ReactiphException
{
    use CarriesDiagnostics;

    public static function notFound(string $componentClass): self
    {
        $e = new self(sprintf('Class "%s" does not exist or is not autoloadable.', $componentClass));

        return $e->withDiagnostics(
            'cli.unknown_component_class',
            ['class' => $componentClass],
            'Check the class name and that it resolves through Composer\'s autoloader.',
        );
    }

    public static function notAComponent(string $componentClass): self
    {
        $e = new self(sprintf('Class "%s" does not extend BaseComponent.', $componentClass));

        return $e->withDiagnostics(
            'cli.not_a_component',
            ['class' => $componentClass],
            'Only classes extending Reactiph\\Component\\BaseComponent can be compiled.',
        );
    }
}
