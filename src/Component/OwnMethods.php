<?php

declare(strict_types=1);

namespace Reactiph\Component;

/**
 * Enumerates the methods a component class declares *itself* (not
 * inherited from {@see BaseComponent}, not magic, not framework-internal)
 * — the single definition of "externally invocable method" shared by
 * {@see \Reactiph\Transpiler\ComponentTranspiler} (client-side transpiled
 * methods) and {@see \Reactiph\Bridge\RpcHandler} (server-side RPC-callable
 * methods), which are the *same set* as of Part 6 (ADR 0018) — there is no
 * mechanism yet to mark a method as one but not the other. Extracted here
 * so that fact stays true by construction instead of by two independently
 * maintained exclusion lists silently drifting apart.
 *
 * Only *public* methods are included — a `private`/`protected` helper is
 * excluded even if declared directly on the component's own class. This
 * matters much more now than it did when only `ComponentTranspiler` used
 * this enumeration (shipping a private helper as inspectable client JS):
 * since {@see \Reactiph\Bridge\RpcHandler} now also treats this same set
 * as remotely invocable, a leaked private method would be a real exposure,
 * not just a mild one.
 */
final class OwnMethods
{
    private const EXCLUDED = ['template', 'render', '__construct'];

    /**
     * @param class-string<BaseComponent> $componentClass
     * @return array<string, \ReflectionMethod>
     */
    public static function of(string $componentClass): array
    {
        $reflection = new \ReflectionClass($componentClass);

        /** @var array<string, \ReflectionMethod> $methods */
        $methods = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $componentClass) {
                continue;
            }

            $name = $method->getName();

            if (in_array($name, self::EXCLUDED, true) || str_starts_with($name, '__')) {
                continue;
            }

            $methods[$name] = $method;
        }

        return $methods;
    }
}
