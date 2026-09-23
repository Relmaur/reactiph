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
 *
 * `template()` has a default implementation (ADR 0020): a component that
 * doesn't override it gets its markup from a sibling file named
 * `{ShortClassName}.reactiph.html`, next to the class's own file — the
 * "folder-based component" convention, where a component's class, template,
 * and (once a real asset pipeline exists) styles live together in one
 * directory. A component that overrides `template()` itself is completely
 * unaffected; this is purely additive to the existing inline-template
 * style. See also {@see ComponentDiscovery} for auto-registering every
 * component found in a directory, instead of calling
 * {@see ComponentRegistry::register()} by hand for each one.
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
     * Override this to return an inline template string, or leave it
     * as-is to use the default `{ShortClassName}.reactiph.html`
     * sibling-file lookup (see this class's docblock and ADR 0020).
     */
    public function template(): string
    {
        $file = $this->defaultTemplateFile();

        if (!is_file($file)) {
            throw MissingTemplateFileException::forComponent(static::class, $file);
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            throw MissingTemplateFileException::forComponent(static::class, $file);
        }

        return $contents;
    }

    /**
     * Resolves the default template file's path via reflection on the
     * concrete component's own class file — never the folder `template()`
     * happens to be *called* from, which for a cached, shared render
     * closure (see this class's docblock) could be any instance's own
     * working directory. `{ShortClassName}.reactiph.html` (not a fixed
     * `template.html`) so multiple components can share one flat
     * directory without colliding, and the custom extension keeps a
     * Reactiph template file unambiguous from an unrelated `.html` file
     * that might live in the same folder for some other reason.
     */
    private function defaultTemplateFile(): string
    {
        $reflection = new \ReflectionClass($this);
        $classFile = $reflection->getFileName();

        if ($classFile === false) {
            throw MissingTemplateFileException::forComponent(static::class, $reflection->getShortName() . '.reactiph.html');
        }

        return dirname($classFile) . '/' . $reflection->getShortName() . '.reactiph.html';
    }

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
