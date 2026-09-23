<?php

declare(strict_types=1);

namespace Reactiph\Component;

use Reactiph\Template\CompiledTemplate;
use Reactiph\Template\Compiler;
use Reactiph\Template\Parser;

/**
 * Base class for a Reactiph component. Public properties are the component's
 * state and are available by name inside the template's `{$expr}`
 * interpolations (e.g. a public `$title` property is readable as `{$title}`).
 *
 * The compiled template is cached per component class (parsing + compiling
 * is comparatively expensive; evaluating the cached render closure per
 * instance is cheap), so subclasses should return a constant template
 * string from `template()` rather than building it dynamically — and must
 * be constructible with no constructor arguments, since a cache-miss
 * compile needs a throwaway instance to call `template()` on (see
 * {@see compileTemplateFor}) independently of whatever instance eventually
 * calls `render()`.
 */
abstract class BaseComponent
{
    /** @var array<class-string, CompiledTemplate> */
    private static array $compiledTemplateCache = [];

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
        $compiled = self::compiledTemplateFor(static::class);

        return $compiled->render->call($this, $this);
    }

    /**
     * Exposed statically (not just via `render()`) so
     * {@see \Reactiph\Transpiler\ComponentTranspiler} can read a class's
     * expression list without an existing instance — see ADR 0017.
     *
     * @param class-string<self> $class
     */
    public static function compiledTemplateFor(string $class): CompiledTemplate
    {
        return self::$compiledTemplateCache[$class] ??= self::compileTemplateFor($class);
    }

    /**
     * @param class-string<self> $class
     */
    private static function compileTemplateFor(string $class): CompiledTemplate
    {
        $instance = new $class();
        $ast = (new Parser())->parse($instance->template());

        return (new Compiler())->compile($ast);
    }
}
