<?php

declare(strict_types=1);

namespace Reactiph\Template\Node;

/**
 * A `{$expr}` interpolation. `expression` is the raw PHP expression source
 * (including the leading `$`), evaluated and HTML-escaped at render time.
 */
final class ExpressionNode implements Node
{
    public function __construct(public readonly string $expression)
    {
    }
}
