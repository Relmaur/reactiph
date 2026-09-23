<?php

declare(strict_types=1);

namespace Reactiph\Template\Node;

/**
 * A single HTML element, or a custom component tag (`<PascalCase />`).
 * Attribute values are either a literal string or a whole-value `{$expr}`
 * expression — mixed literal/expression attribute content (e.g.
 * `class="btn {$type}"`) is not supported yet.
 */
final class TagNode implements Node
{
    /**
     * @param array<string, string|ExpressionNode> $attributes
     * @param Node[] $children
     * @param bool $selfClosing Written as `<tag />` or `<tag>` with no
     *   matching closing tag (a void HTML element like `<img>`) — distinct
     *   from a normal tag that merely has no children, e.g. `<div></div>`,
     *   which still needs its closing tag emitted.
     * @param array<string, string> $events Event bindings written as
     *   `(click)="increment"` — event name to the (bare, not `{$expr}`)
     *   component method name to call. Only valid on a literal HTML tag;
     *   the parser rejects this on a component tag (`<PascalCase />`).
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes,
        public readonly array $children,
        public readonly bool $selfClosing = false,
        public readonly array $events = [],
    ) {
    }

    /**
     * A tag name is a component reference, not a plain HTML element, when
     * it starts with an uppercase letter (`<LikeButton />` vs `<div>`).
     * This convention is the single source of truth for that distinction —
     * both the parser (to avoid mis-treating a PascalCase name as a void
     * HTML element) and the compiler (to choose how to compile the tag)
     * rely on it.
     */
    public static function isComponentName(string $name): bool
    {
        return $name !== '' && ctype_upper($name[0]);
    }
}
