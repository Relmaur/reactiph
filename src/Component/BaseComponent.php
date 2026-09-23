<?php

declare(strict_types=1);

namespace Reactiph\Component;

use Reactiph\Template\Compiler;
use Reactiph\Template\Parser;

/**
 * Base class for a Reactiph component. Public properties are the component's
 * state and are available by name inside the template's `{$expr}`
 * interpolations (e.g. a public `$title` property is readable as `{$title}`).
 *
 * The compiled render closure is cached per component class (parsing +
 * compiling a template is comparatively expensive; evaluating the cached
 * closure per instance is cheap), so subclasses should return a constant
 * template string from `template()` rather than building it dynamically.
 */
abstract class BaseComponent
{
    /** @var array<class-string, \Closure> */
    private static array $rendererCache = [];

    /**
     * Pre-rendered HTML of the content a parent template placed between a
     * custom tag's open/close tags (e.g. `<LikeButton>Click me</LikeButton>`),
     * available to this component's own template as `{$slot}`. Empty for a
     * self-closing tag or a component rendered directly (not through a
     * parent template).
     */
    public string $slot = '';

    /**
     * A caller-assigned, unique-per-page identifier marking this component
     * as a hydration root. When set (and the template's single top-level
     * node is a literal HTML tag — see {@see \Reactiph\Template\Compiler}),
     * `render()` emits it as `data-reactiph-id` on the root element, so a
     * client-side hydration script can find the corresponding DOM node.
     * Null (the default) for a component that's never hydrated — plain SSR
     * output is unaffected either way. See
     * {@see \Reactiph\Runtime\HydrationSerializer}.
     */
    public ?string $hydrationId = null;

    /**
     * The component's markup. See {@see Parser} for the supported syntax.
     */
    abstract public function template(): string;

    public function render(): string
    {
        $renderer = self::$rendererCache[static::class] ??= $this->compileRenderer();

        return $renderer->call($this, $this);
    }

    private function compileRenderer(): \Closure
    {
        $ast = (new Parser())->parse($this->template());

        return (new Compiler())->compile($ast);
    }
}
