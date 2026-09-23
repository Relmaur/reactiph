<?php

declare(strict_types=1);

namespace Reactiph\Bridge;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

/**
 * Thrown by {@see RpcHandler} for a malformed or invalid RPC payload —
 * never a stack trace leaking into an HTTP response, since every host
 * bridge's own request handler is expected to catch this, log/serialize
 * `jsonSerialize()`, and turn it into an HTTP 4xx.
 */
final class RpcException extends \RuntimeException implements ReactiphException
{
    use CarriesDiagnostics;

    public static function malformedPayload(): self
    {
        $e = new self('Malformed RPC payload.');

        return $e->withDiagnostics(
            'bridge.rpc_malformed_payload',
            [],
            'Expected {"component": string, "method": string, "state": object, "args": array}.',
        );
    }

    public static function unknownComponent(string $componentClass): self
    {
        $e = new self(sprintf('"%s" is not a known Reactiph component.', $componentClass));

        return $e->withDiagnostics(
            'bridge.rpc_unknown_component',
            ['component' => $componentClass],
            'The "component" field must be the fully-qualified class name of a class extending BaseComponent.',
        );
    }

    public static function unknownMethod(string $componentClass, string $method): self
    {
        $e = new self(sprintf('%s::%s() is not an RPC-callable method.', $componentClass, $method));

        return $e->withDiagnostics(
            'bridge.rpc_unknown_method',
            ['component' => $componentClass, 'method' => $method],
            'Only public methods declared directly on the component\'s own class (not inherited from '
                . 'BaseComponent, and not magic methods) are RPC-callable — the same set ComponentTranspiler '
                . 'transpiles for client-side execution (ADR 0016).',
        );
    }

    public static function unknownStateProperty(string $componentClass, string $property): self
    {
        $e = new self(sprintf('%s has no public property "%s".', $componentClass, $property));

        return $e->withDiagnostics(
            'bridge.rpc_unknown_state_property',
            ['component' => $componentClass, 'property' => $property],
            'The "state" payload can only set properties the component actually declares — this is '
                . 'rejected rather than silently creating a dynamic property, which PHP 8.2+ deprecates anyway.',
        );
    }
}
