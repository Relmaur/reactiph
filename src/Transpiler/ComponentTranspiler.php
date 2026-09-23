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

        return $this->assemble($componentClass, $methodsJs);
    }

    /**
     * @param array<string, string> $methodsJs
     */
    private function assemble(string $componentClass, array $methodsJs): string
    {
        $entries = [];

        foreach ($methodsJs as $name => $js) {
            // $js is already a full named function expression
            // ("function increment() {...}"), valid directly as an
            // object property's value.
            $entries[] = var_export($name, true) . ': ' . rtrim($js, "\n");
        }

        $methodsObject = $entries === [] ? '{}' : "{\n" . implode(",\n", $entries) . "\n}";

        return 'window.ReactiphComponents = window.ReactiphComponents || {};' . "\n"
            . 'window.ReactiphComponents[' . var_export($componentClass, true) . '] = { methods: ' . $methodsObject . ' };' . "\n";
    }
}
