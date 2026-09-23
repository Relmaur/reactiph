<?php

declare(strict_types=1);

namespace Reactiph\Template\Node;

/**
 * A run of literal markup between tags/expressions. Rendered verbatim (not
 * HTML-escaped) since it's authored as part of the template itself.
 */
final class TextNode implements Node
{
    public function __construct(public readonly string $text)
    {
    }
}
