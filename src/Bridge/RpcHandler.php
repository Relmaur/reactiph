<?php

declare(strict_types=1);

namespace Reactiph\Bridge;

use Reactiph\Component\BaseComponent;
use Reactiph\Component\OwnMethods;

/**
 * The host-agnostic half of "expose an RPC endpoint for server-bound
 * method calls" (ADR 0004): given a decoded RPC payload (component class,
 * method name, current state, positional args), instantiates the
 * component, applies the state, calls the real PHP method, and returns
 * the resulting state — plain arrays in and out, no HTTP types, so every
 * `BridgeInterface::handleRpc()` implementation (DefaultBridge today, the
 * planned `reactiph/wordpress-bridge` later) can delegate here instead of
 * reimplementing this dispatch logic per host. See ADR 0018.
 *
 * As of Part 6, any public method a component declares on its own class
 * is RPC-callable — the same set {@see \Reactiph\Transpiler\ComponentTranspiler}
 * transpiles for client-side execution. There is deliberately no
 * mechanism yet to mark a method as server-only/excluded from client
 * transpilation (see ADR 0018) — a method usable via RPC today is also
 * shipped to the client as JS, unless calling it hits something outside
 * the transpiler's allow-listed subset (ADR 0011), in which case
 * transpilation itself fails loudly rather than silently.
 */
final class RpcHandler
{
    /**
     * Component state properties that are Reactiph plumbing, not
     * meaningful state — excluded from the returned state the same way
     * {@see \Reactiph\Runtime\HydrationSerializer} excludes them from a
     * hydration payload.
     */
    private const INTERNAL_PROPERTIES = ['slot', 'hydrationId'];

    /**
     * @param array<string, mixed> $payload
     * @return array{state: array<string, mixed>}
     */
    public function handle(array $payload): array
    {
        $componentClass = $payload['component'] ?? null;
        $method = $payload['method'] ?? null;
        $state = $payload['state'] ?? [];
        $args = $payload['args'] ?? [];

        if (!is_string($componentClass) || !is_string($method) || !is_array($state) || !is_array($args)) {
            throw RpcException::malformedPayload();
        }

        if (!class_exists($componentClass) || !is_subclass_of($componentClass, BaseComponent::class)) {
            throw RpcException::unknownComponent($componentClass);
        }

        if (!isset(OwnMethods::of($componentClass)[$method])) {
            throw RpcException::unknownMethod($componentClass, $method);
        }

        $instance = new $componentClass();

        foreach ($state as $property => $value) {
            if (!is_string($property) || !property_exists($instance, $property)) {
                throw RpcException::unknownStateProperty($componentClass, (string) $property);
            }

            $instance->{$property} = $value;
        }

        $instance->{$method}(...array_values($args));

        $newState = get_object_vars($instance);

        foreach (self::INTERNAL_PROPERTIES as $internal) {
            unset($newState[$internal]);
        }

        return ['state' => $newState];
    }
}
