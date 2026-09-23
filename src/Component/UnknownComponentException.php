<?php

declare(strict_types=1);

namespace Reactiph\Component;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

final class UnknownComponentException extends \RuntimeException implements ReactiphException
{
    use CarriesDiagnostics;

    public static function forTag(string $tagName): self
    {
        $e = new self(sprintf('No component is registered for tag <%s>.', $tagName));

        return $e->withDiagnostics(
            'component.unregistered',
            ['tag' => $tagName],
            sprintf(
                '%s::register(%s, YourComponent::class) before rendering a template that uses <%s>.',
                ComponentRegistry::class,
                var_export($tagName, true),
                $tagName,
            ),
        );
    }
}
