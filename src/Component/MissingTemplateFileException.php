<?php

declare(strict_types=1);

namespace Reactiph\Component;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

/**
 * Thrown by {@see BaseComponent}'s default `template()` implementation when
 * a component neither overrides `template()` itself nor has a matching
 * `{ShortClassName}.reactiph.html` file next to its own class file.
 */
final class MissingTemplateFileException extends \RuntimeException implements ReactiphException
{
    use CarriesDiagnostics;

    /**
     * @param class-string<BaseComponent> $componentClass
     */
    public static function forComponent(string $componentClass, string $expectedPath): self
    {
        $e = new self(sprintf(
            '%s has no template() override and no template file was found at %s.',
            $componentClass,
            $expectedPath,
        ));

        return $e->withDiagnostics(
            'component.missing_template_file',
            ['class' => $componentClass, 'expected_path' => $expectedPath],
            sprintf(
                'Either override template(): string in %s, or add a sibling template file named %s.',
                $componentClass,
                basename($expectedPath),
            ),
        );
    }
}
