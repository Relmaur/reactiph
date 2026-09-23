<?php

declare(strict_types=1);

namespace Reactiph\Transpiler;

use Reactiph\Component\BaseComponent;

/**
 * Assembles a component class's own methods into one JS "component
 * definition", registered by class name onto a global
 * `window.ReactiphComponents` map for a hydration client to call into —
 * see ADR 0016. `PhpToJs::transpileMethod()` only handles one method at a
 * time; this is what turns that into something a client runtime can
 * actually invoke by name.
 *
 * Only methods *declared on the component's own class* are transpiled —
 * inherited `BaseComponent` methods (`render()`, `template()`) are
 * framework-internal, never client-side logic, and are excluded by name
 * regardless of where they're declared.
 *
 * Also assembles an `expressions` map alongside `methods`, one entry per
 * marker index `BaseComponent::compiledTemplateFor()` recorded for this
 * class (ADR 0017) — each a JS thunk recomputing that `{$expr}`'s value
 * against `this`, for a hydration client to patch the DOM's comment-marked
 * text after a bound method runs. Wrapped in the same `__phpString()`
 * runtime helper `Template\Compiler::emitEscaped()`'s `(string)` cast uses
 * server-side, so a boolean/etc. stringifies identically on both sides
 * (see ADR 0014 — the bug that shim already exists to prevent).
 */
final class ComponentTranspiler
{
    private const EXCLUDED_METHODS = ['template', 'render', '__construct'];

    /**
     * @param class-string<BaseComponent> $componentClass
     */
    public function transpileComponent(string $componentClass): string
    {
        $reflection = new \ReflectionClass($componentClass);
        $phpToJs = new PhpToJs();

        /** @var array<string, string> $methodsJs */
        $methodsJs = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $componentClass) {
                continue;
            }

            $name = $method->getName();

            if (in_array($name, self::EXCLUDED_METHODS, true) || str_starts_with($name, '__')) {
                continue;
            }

            $methodsJs[$name] = $phpToJs->transpileMethod(MethodSourceReader::read($method));
        }

        $compiledTemplate = BaseComponent::compiledTemplateFor($componentClass);

        /** @var array<int, string> $expressionsJs */
        $expressionsJs = [];

        foreach ($compiledTemplate->expressions as $index => $phpExpr) {
            $expressionsJs[$index] = $phpToJs->transpileExpression($phpExpr);
        }

        return $this->assemble($componentClass, $methodsJs, $expressionsJs);
    }

    /**
     * @param array<string, string> $methodsJs
     * @param array<int, string> $expressionsJs
     */
    private function assemble(string $componentClass, array $methodsJs, array $expressionsJs): string
    {
        $methodEntries = [];

        foreach ($methodsJs as $name => $js) {
            // $js is already a full named function expression
            // ("function increment() {...}"), valid directly as an
            // object property's value.
            $methodEntries[] = var_export($name, true) . ': ' . rtrim($js, "\n");
        }

        $methodsObject = $methodEntries === [] ? '{}' : "{\n" . implode(",\n", $methodEntries) . "\n}";

        $expressionEntries = [];

        foreach ($expressionsJs as $index => $js) {
            $expressionEntries[] = $index . ': function () { return __phpString(' . $js . '); }';
        }

        $expressionsObject = $expressionEntries === [] ? '{}' : "{\n" . implode(",\n", $expressionEntries) . "\n}";

        return 'window.ReactiphComponents = window.ReactiphComponents || {};' . "\n"
            . 'window.ReactiphComponents[' . var_export($componentClass, true) . '] = { methods: ' . $methodsObject
            . ', expressions: ' . $expressionsObject . ' };' . "\n";
    }
}
