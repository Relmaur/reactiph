<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures;

use Reactiph\Component\BaseComponent;

/**
 * Deliberately does NOT override template() -- exercises BaseComponent's
 * default sibling-file lookup (ADR 0020) against the real
 * FileTemplateComponent.reactiph.html next to this file.
 */
final class FileTemplateComponent extends BaseComponent
{
    public string $name = 'World';
}
