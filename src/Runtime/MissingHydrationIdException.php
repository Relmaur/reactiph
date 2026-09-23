<?php

declare(strict_types=1);

namespace Reactiph\Runtime;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

final class MissingHydrationIdException extends \LogicException implements ReactiphException
{
    use CarriesDiagnostics;

    /**
     * @param class-string $componentClass
     */
    public static function forComponent(string $componentClass): self
    {
        $e = new self(sprintf(
            '%s must have $hydrationId set before it can be serialized for hydration.',
            $componentClass,
        ));

        return $e->withDiagnostics(
            'runtime.missing_hydration_id',
            ['class' => $componentClass],
            'Set $component->hydrationId to a unique string before calling HydrationSerializer::payloadFor().',
        );
    }
}
