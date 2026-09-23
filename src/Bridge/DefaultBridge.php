<?php

declare(strict_types=1);

namespace Reactiph\Bridge;

/**
 * The framework-agnostic `BridgeInterface` implementation for a plain PHP
 * app (no host framework) — see ADR 0004/0018. Targets PHP's built-in
 * development server: `examples/bridge-server.php` is a small front
 * controller that calls {@see serveAsset()} and {@see handleRpc()}
 * directly rather than through a real router, since a bare PHP app has no
 * asset-serving/routing convention of its own the way WordPress does.
 */
final class DefaultBridge implements BridgeInterface
{
    /**
     * The only files this bridge knows how to serve — the static runtime
     * shipped in `packages/runtime-js/`. Per-component JS is intentionally
     * not here; see {@see BridgeInterface::assetUrl()}.
     */
    private const RUNTIME_ASSETS = ['php-runtime.js', 'hydrate.js'];

    private readonly RpcHandler $rpcHandler;

    public function __construct(private readonly string $basePath = '/reactiph')
    {
        $this->rpcHandler = new RpcHandler();
    }

    public function assetUrl(string $name): string
    {
        return $this->basePath . '/assets/' . $name;
    }

    public function rpcEndpointUrl(): string
    {
        return $this->basePath . '/rpc';
    }

    public function handleRpc(array $payload): array
    {
        return $this->rpcHandler->handle($payload);
    }

    /**
     * Not part of `BridgeInterface` — how a request path maps to bytes is
     * inherently host-specific (WordPress has its own enqueue/REST
     * plumbing to do this), so it isn't part of the abstraction every
     * bridge must implement. A plain PHP app has no such convention at
     * all, so `DefaultBridge` provides its own minimal one a front
     * controller can call into directly. Returns null for any name that
     * isn't one of the static files this bridge is responsible for.
     */
    public function serveAsset(string $name): ?string
    {
        if (!in_array($name, self::RUNTIME_ASSETS, true)) {
            return null;
        }

        $contents = file_get_contents(dirname(__DIR__, 2) . '/packages/runtime-js/' . $name);

        return $contents === false ? null : $contents;
    }
}
