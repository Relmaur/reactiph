<?php

declare(strict_types=1);

namespace Reactiph\Bridge;

/**
 * The seam between Reactiph's framework-agnostic core and any host
 * framework (a plain PHP app via {@see DefaultBridge}, WordPress via the
 * separate `reactiph/wordpress-bridge` package) — see ADR 0004. Abstracts
 * exactly the two concerns that are inherently host-specific: where a
 * hydration client loads the runtime JS from, and where it sends a
 * server-bound RPC call.
 *
 * Deliberately free of any HTTP-framework types (no PSR-7, no raw
 * superglobals) — `handleRpc()` takes and returns plain arrays so it has
 * no opinion on how a given host receives a request or sends a response;
 * see ADR 0018.
 */
interface BridgeInterface
{
    /**
     * The URL a hydration client should load a runtime JS asset from
     * (`php-runtime.js`, `hydrate.js`). Per-component transpiled JS
     * (`ComponentTranspiler`'s output) is deliberately not one of these —
     * it stays inlined directly into the page, as it already was in Part
     * 5, rather than becoming a third kind of asset this method serves.
     */
    public function assetUrl(string $name): string;

    /**
     * The URL a hydration client should POST an RPC payload to, to invoke
     * a server-bound component method (see {@see RpcHandler}).
     */
    public function rpcEndpointUrl(): string;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handleRpc(array $payload): array;
}
