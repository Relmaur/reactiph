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
