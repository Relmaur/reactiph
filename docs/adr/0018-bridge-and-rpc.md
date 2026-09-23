# ADR 0018: BridgeInterface shape, RPC dispatch, and server-bound methods

- Status: accepted
- Date: 2026-09-22

## Context

ADR 0004 (written during planning, before Part 3) already named
`BridgeInterface`'s two concerns — serving the runtime JS asset, and
exposing an RPC endpoint for server-bound method calls — and flagged the
interface as load-bearing for both `DefaultBridge` (this part) and the
future `reactiph/wordpress-bridge` package (Part 7), worth getting right
now rather than iterating later. What ADR 0004 didn't settle: how a
component method gets marked as "server-bound" (needs a real RPC
round-trip) versus "client-transpiled" (Parts 4/5's existing pipeline) —
a real fork surfaced to the user before implementation, since it affects
the component-authoring surface, not just internal plumbing.

## Decision

**No new marking mechanism yet.** Every component method stays exactly as
Part 4/5 left it: `ComponentTranspiler` transpiles every method
`Component\OwnMethods` enumerates (own-declared, public, not
`template`/`render`/`__construct`, not magic) to client JS, unconditionally.
`Bridge\RpcHandler` treats that *exact same set* as RPC-callable — the two
consumers now share one enumeration (`OwnMethods::of()`) instead of two
independently maintained exclusion lists, closing off a real way they
could silently drift apart. A method that does something outside the
transpiler's allow-listed subset (ADR 0011) — e.g. real file I/O — simply
fails to transpile, loudly, at `ComponentTranspiler` build time; there's
no way yet to write such a method on a class that's also passed through
transpilation. Demonstrated by keeping the two demo components in
`examples/bridge-server.php` structurally separate: `Counter` is
transpiled and hydrated as before; `Guestbook` (whose only method does
file I/O) is never passed to `ComponentTranspiler` at all — SSR-only,
with its one interaction hand-wired to the RPC endpoint directly.

**`BridgeInterface`** (`src/Bridge/BridgeInterface.php`) has exactly three
methods, deliberately free of any HTTP-framework type:

```php
interface BridgeInterface
{
    public function assetUrl(string $name): string;
    public function rpcEndpointUrl(): string;
    public function handleRpc(array $payload): array;
}
```

`assetUrl()` only covers the two static runtime files
(`php-runtime.js`, `hydrate.js`) — per-component transpiled JS stays
inlined directly into the page, exactly as it already was in Part 5, not
promoted to a third kind of served asset. `handleRpc()` takes and returns
plain arrays (not a PSR-7 request/response, not raw superglobals), so the
interface itself has zero opinion on how a given host receives a request
or sends a response — each bridge's own request handling wraps it.

**RPC dispatch logic lives once, in `Bridge\RpcHandler`, not per-bridge.**
`handleRpc(array $payload): array` on any `BridgeInterface` implementation
is expected to delegate to `RpcHandler::handle()`, which does the actual
work: validates the payload shape, resolves the component class (rejects
anything that isn't a real `BaseComponent` subclass), resolves the method
against `OwnMethods::of()` (rejecting anything not in that set — including
`template()`, which passes a naive "declared on this class" check since
it's the required abstract override, but is explicitly excluded by name),
applies the incoming `state` onto a fresh instance (rejecting any key that
isn't an actually-declared property, rather than silently creating a
dynamic one), calls the real method with positional `args`, and returns
the resulting state with the same `slot`/`hydrationId` plumbing
`HydrationSerializer` already excludes. This is why `reactiph/wordpress-bridge`
(Part 7) won't need to reimplement this validation from scratch — only its
own request/response marshaling around it.

**`Component\OwnMethods`** is a new, small extraction: both
`ComponentTranspiler` and `RpcHandler` now call `OwnMethods::of($class)`
instead of each hand-rolling their own reflection loop. Caught a real
correctness gap while writing this: `ReflectionClass::getMethods()` with
no filter returns methods of *every* visibility, so a `private`/`protected`
helper declared on a component's own class was already (silently, since
Part 5) being transpiled to inspectable client JS — a mild issue on its
own, but a real one once the same enumeration also means "remotely
RPC-invocable." `OwnMethods::of()` now filters to
`ReflectionMethod::IS_PUBLIC` explicitly.

**`DefaultBridge`** targets PHP's built-in development server, not a
generic PSR-7 app — `examples/bridge-server.php` is a small front
controller (`php -S localhost:PORT examples/bridge-server.php`) that calls
`serveAsset()` and `handleRpc()` directly, since a bare PHP app has no
existing routing/asset-serving convention the way WordPress does.
`serveAsset()` itself is deliberately *not* part of `BridgeInterface` —
how a request path maps to bytes is inherently host-specific (WordPress
uses `wp_enqueue_script` + its own REST plumbing, never this method), so
it isn't part of the contract every bridge must implement.

**Live-verified against a real running server**, not just unit tests
(per `CLAUDE.md`'s Parts 3–6 live-check requirement): started
`examples/bridge-server.php` under PHP's built-in server, `curl`'d the
asset routes (200 for known files, 404 for unknown), the RPC endpoint
(a `sign()` call correctly persisting and incrementing a real
server-side counter across two separate requests, method-not-allowed on
`GET`, a structured error for an unknown component), then drove a real
headless browser through both demo interactions: `Counter`'s button still
patches the DOM via the Part 5 mechanism (now loading its runtime JS from
real Bridge-served HTTP responses instead of a relative file path), and
`Guestbook`'s button round-trips to the real server and displays the
actual persisted count.

## Alternatives considered

- **`#[ServerAction]` attribute or a naming convention to mark
  server-only methods.** Rejected for now (user's explicit choice):
  invents new component-authoring syntax and an exclusion-logic change
  ahead of Part 7 actually needing it — WordPress DB access is what will
  force this question for real, with a concrete case in hand instead of a
  speculative one.
- **`assetUrl()` also covering per-component transpiled JS**, so a host
  could serve it as a cacheable separate file instead of inlining.
  Rejected: an extra HTTP round trip per page load for something that's
  already cheap to inline, and Part 5's existing inlining approach works
  without a change — revisit if payload size becomes real (already flagged
  as a possible future concern in ADR 0016's Consequences).
- **`handleRpc()` typed around a PSR-7 request/response.** Rejected: pulls
  in an HTTP-message dependency core doesn't otherwise need, and forces
  every host (including the WordPress bridge, which has its own REST
  request type) to adapt into PSR-7 just to call in — plain arrays are
  the actual common denominator.
- **A generic `BridgeInterface::serveAsset()` method.** Considered, but
  rejected: asset-serving conventions are exactly the kind of thing that
  differs per host (a plain PHP app needs bytes; WordPress needs a URL
  `wp_enqueue_script` can consume, and never calls back into Reactiph to
  ask "what are the bytes"). Keeping it as a `DefaultBridge`-only method
  avoids forcing an artificial shared shape onto a genuinely host-specific
  concern.

## Consequences

- `RpcHandler`'s current validation (real `BaseComponent` subclass,
  `OwnMethods`-enumerated method only, state limited to actually-declared
  properties) is real input validation, but **not authentication or
  authorization** — anything reachable through a page's RPC endpoint can
  invoke any of a component's own public methods with attacker-supplied
  state. Fine for this part's scope (a local dev-server example with no
  real users), but a real gap `reactiph/wordpress-bridge` (Part 7) will
  need to close with actual auth (e.g. WordPress nonces) before this is
  usable on a real site — noted here rather than glossed over.
- Every component class that will ever be passed to `ComponentTranspiler`
  must keep *all* of its own-declared public methods inside the
  transpiler's allow-listed subset — there's no way yet to have "most
  methods transpile, but this one is server-only." A component that needs
  genuine server-side work (file I/O, a database call once Part 7 needs
  one) must stay entirely outside the transpiled/hydrated pipeline, as
  `Guestbook` does here. Revisiting this is explicitly deferred, not
  forgotten — flagged again in `docs/STATUS.md`.
- `ComponentTranspiler`'s `EXCLUDED_METHODS` constant is gone — replaced
  by `Component\OwnMethods`, which is now also a public API surface
  `RpcHandler` (and any future bridge) depends on; changing what counts as
  an "own method" now has two call sites to consider, not one.
