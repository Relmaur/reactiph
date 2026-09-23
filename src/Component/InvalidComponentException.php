<?php

declare(strict_types=1);

namespace Reactiph\Component;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

final class InvalidComponentException extends \InvalidArgumentException implements ReactiphException
{
    use CarriesDiagnostics;

    /**
     * @param class-string $componentClass
     */
    public static function mustExtendBaseComponent(string $componentClass): self
    {
        $e = new self(sprintf(
            '%s must extend %s to be registered as a component.',
            $componentClass,
            BaseComponent::class,
        ));

        return $e->withDiagnostics(
            'component.invalid_class',
            ['class' => $componentClass],
            sprintf('Make %s extend %s.', $componentClass, BaseComponent::class),
        );
    }
}
