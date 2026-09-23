<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures;

use Reactiph\Component\BaseComponent;

/**
 * Deliberately does NOT override template() AND has no matching
 * NoTemplateFileComponent.reactiph.html sibling file -- exercises
 * BaseComponent's MissingTemplateFileException (ADR 0020).
 */
final class NoTemplateFileComponent extends BaseComponent
{
}
