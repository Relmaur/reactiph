<?php

declare(strict_types=1);

namespace Reactiph\Template\Node;

/**
 * A single HTML element. Attribute values are either a literal string or a
 * whole-value `{$expr}` expression — mixed literal/expression attribute
 * content (e.g. `class="btn {$type}"`) is not supported yet.
 */
final class TagNode implements Node
{
    /**
     * @param array<string, string|ExpressionNode> $attributes
     * @param Node[] $children
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes,
        public readonly array $children,
    ) {
    }
}
