<?php

declare(strict_types=1);

namespace ReactiphExamples;

use Reactiph\Component\BaseComponent;

/**
 * Deliberately does not override template() -- its markup comes from the
 * sibling Greeting.reactiph.html file instead (ADR 0020), and this class
 * is never manually registered with ComponentRegistry -- see
 * examples/folder-components.php, which discovers it automatically.
 */
final class Greeting extends BaseComponent
{
    public string $name = 'World';
}
