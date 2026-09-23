# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 6 — Bridge abstraction + `DefaultBridge`: complete and verified.**
`BridgeInterface` (ADR 0004's two named concerns: serving the runtime JS
asset, exposing an RPC endpoint for server-bound method calls) is now
implemented, live-verified against a real running PHP built-in server, not
just unit tests.

One real fork was surfaced to the user before implementation: how a
component method gets marked "server-bound" vs. "client-transpiled."
Decided: no new marking mechanism yet — every component method is both,
using the exact same enumeration (`Component\OwnMethods`, new). A method
needing genuine server-only behavior must live on a component that's
never passed to `ComponentTranspiler` at all (see ADR 0018).

- **`Bridge\BridgeInterface`** — `assetUrl()`, `rpcEndpointUrl()`,
  `handleRpc()`, deliberately free of any HTTP-framework type (plain
  arrays in/out for RPC).
- **`Bridge\RpcHandler`** (new) — the host-agnostic RPC dispatch logic
  every `BridgeInterface::handleRpc()` is expected to delegate to:
  validates the payload, resolves the component class (must be a real
  `BaseComponent` subclass) and method (must be in `OwnMethods::of()`),
  applies incoming state (rejects any key that isn't an actually-declared
  property), calls the real method, returns the new state.
- **`Component\OwnMethods`** (new) — extracted from `ComponentTranspiler`'s
  previous inline reflection loop; now the single shared definition of
  "externally invocable method" for both client transpilation and RPC.
  **Caught a real gap while extracting it**: the old loop had no
  visibility filter, so a `private`/`protected` helper on a component's
  own class was already being silently transpiled to client JS since Part
  5 — now filtered to `ReflectionMethod::IS_PUBLIC` explicitly, which
  matters much more now that the same set is also RPC-reachable.
- **`Bridge\DefaultBridge`** — targets PHP's built-in development server.
  `serveAsset()` (not part of `BridgeInterface` — asset-serving is
  inherently host-specific) serves `php-runtime.js`/`hydrate.js` from
  disk; per-component JS stays inlined into the page as it already was in
  Part 5, not promoted to a third asset type.
- **`examples/bridge-server.php`** (new) — a real front controller run via
  `php -S localhost:PORT examples/bridge-server.php`, serving two
  components: `Counter` (Part 5's demo, unchanged behavior, now loading
  its runtime JS from actual Bridge-served HTTP responses) and `Guestbook`
  (new) — a component with no client-transpiled methods at all, whose
  `sign()` does real file I/O (outside the transpiler's allow-listed
  subset — ADR 0011) and is wired to the RPC endpoint by hand-written page
  JS, not new template syntax.
- **Live-verified against a real running server**: `curl`'d the asset
  routes (200/404), the RPC endpoint (a `sign()` call persisting and
  incrementing a real server-side counter across separate requests, a
  structured error for an unknown component, 405 on `GET`), then drove a
  real headless browser through both demos — `Counter`'s button still
  patches the DOM 3 → 4 exactly as Part 5 proved, and `Guestbook`'s button
  round-trips to the real server and displays the actual persisted count
  across repeated clicks.
- 160 PHPUnit tests passing (20 new this part). PHPStan (level 8) and
  PHP-CS-Fixer both clean.
- Not yet committed as of this status update.

## Next up

**User go-ahead needed before starting Part 7**
(`reactiph/wordpress-bridge` package), per the working agreement.

Part 7 is where the deferred "which methods are server-only" question
will likely become unavoidable for real (WordPress DB access is the
concrete case ADR 0018 anticipated) — worth revisiting rather than
assuming the Part 6 answer (no marking) still holds once that's real.
Part 7 also needs real auth on the RPC endpoint (see ADR 0018's
Consequences: today's `RpcHandler` validates input shape but has no
authentication/authorization at all — fine for a local example, not fine
for a real site) — likely WordPress nonces, but not yet designed.

## Remaining parts (unstarted)

7. `reactiph/wordpress-bridge` package.
8. CLI/dev tooling + docs.

## Open threads / not yet decided

- Template syntax has no control-flow directive (`@foreach`/`@if` or
  similar) for dynamically rendering a variable-length list of children —
  the plan's Part 2 scope didn't call for it, so the Part 2 Blog example
  hardcodes a fixed number of `<Thumbnail />` tags rather than looping
  over an array. Not designed yet. This also gates structural DOM patching
  (ADR 0017) — there's nothing to patch structurally until this exists.
- Only one, unnamed default slot exists per component (ADR 0008) — no
  named/multiple slots.
- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently a silent no-op (ADR 0010) — needs a real
  decision (most likely: enforce single-root globally) once more than one
  component needs hydrating on a page at once.
- No allow-list yet for which public properties should actually reach the
  client as hydration state vs. stay server-only (ADR 0010).
- `packages/runtime-js` has no `package.json`/npm tooling yet — deferred
  deliberately until real JS build tooling is needed.
- Closures are entirely unimplemented in the transpiler — blocks
  `array_map`/`array_filter` (ADR 0015).
- Event bindings only support `click` in practice — the delegation
  mechanism (ADR 0016) is generic, but `hydrate.js` only attaches a click
  listener today. Small, mechanical follow-up, not a design question.
- DOM patching is scoped to text-node content only — attribute-value and
  structural patching are both explicitly deferred (ADR 0017).
- Nested-component hydration (a component with its own `hydrationId`
  rendered inside another hydrated component's subtree) has a defensive
  boundary check in `hydrate.js`'s marker walker but no real example or
  test exercises it yet.
- **No mechanism marks a component method as server-only vs.
  client-transpiled** (ADR 0018) — a component needing genuine
  server-side behavior must have zero client-transpiled methods at all
  (see `Guestbook` in `examples/bridge-server.php`). Likely to become a
  real, forced decision in Part 7.
- **`RpcHandler` has no authentication/authorization** (ADR 0018) —
  validates payload shape and method-callability only. A real gap for any
  non-local deployment, explicitly deferred to Part 7.
