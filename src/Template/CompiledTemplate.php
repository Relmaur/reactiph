<?php

declare(strict_types=1);

namespace Reactiph\Template;

/**
 * The result of compiling a template: the PHP render closure used for SSR,
 * and the ordered list of text-position `{$expr}` interpolations the
 * closure's output marks with an HTML comment pair (`<!--rN--><!--/rN-->`,
 * emitted only when the rendering component's `hydrationId` is set — see
 * {@see Compiler}) so a hydration client can find and patch each one after
 * a state change, without a template->JS compiler. Both `render` and
 * `expressions` come from the same compile pass, so the marker indices in
 * `render`'s output and the keys of `expressions` are guaranteed to agree.
 * See ADR 0017.
 */
final class CompiledTemplate
{
    /**
     * @param array<int, string> $expressions raw PHP expression source, keyed by marker index
     */
    public function __construct(
        public readonly \Closure $render,
        public readonly array $expressions,
    ) {
    }
}
