<?php

declare(strict_types=1);

namespace Reactiph\Cli;

use Reactiph\Component\BaseComponent;
use Reactiph\Transpiler\ComponentTranspiler;

/**
 * `bin/reactiph compile <ComponentClass>` — compiles one already-
 * autoloadable component's template and transpiles its methods, printing
 * the resulting JS to stdout. Exists for two things `bin/reactiph build`
 * doesn't cover: a fast single-component check while authoring one (no
 * directory scan), and a CI-friendly way to catch a real template or
 * transpiler error (a `ReactiphException`, per ADR 0009) before it ships,
 * without needing a whole `$sourceDir` of other components to exist.
 */
final class CompileCommand
{
    private readonly ComponentTranspiler $transpiler;

    public function __construct(?ComponentTranspiler $transpiler = null)
    {
        $this->transpiler = $transpiler ?? new ComponentTranspiler();
    }

    /**
     * `$componentClass` is plain `string`, not `class-string` — it comes
     * straight from CLI argv (or any other untrusted caller), which is
     * exactly why the checks below exist rather than being asserted away.
     */
    public function run(string $componentClass): string
    {
        if (!class_exists($componentClass)) {
            throw UnknownComponentClassException::notFound($componentClass);
        }

        if (!is_subclass_of($componentClass, BaseComponent::class)) {
            throw UnknownComponentClassException::notAComponent($componentClass);
        }

        // Forces the template to actually compile (a real ParseException
        // surfaces here, not silently deferred to first render) before the
        // methods are transpiled.
        BaseComponent::compiledTemplateFor($componentClass);

        return $this->transpiler->transpileComponent($componentClass);
    }
}
