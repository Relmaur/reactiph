# ADR 0021: `reactiph/taw-bridge` — ReactiveMetaBlock instead of a WordPress-generic shortcode

- Status: accepted
- Date: 2026-09-23

## Context

`reactiph/wordpress-bridge` (ADR 0019) targets raw WordPress via a
shortcode. The user doesn't want that as the integration point for their
actual use case — TAW, their own WordPress theme framework, which already
has a mature block system (`TAW\Core\Block\MetaBlock`, folder-based
auto-discovery, a real Vite asset pipeline, a metabox engine). The ask:
rethink the WordPress integration as a TAW-specific one instead of a
generic-WordPress one, brainstormed before any code was written, grounded
in TAW's actual current source (`~/Documents/TAW/taw-core`), not assumed
from documentation.

## Decision

**A new package, `reactiph/taw-bridge`**, depending on both
`reactiph/reactiph` and `taw/core` as real Composer dependencies (`taw/core`
*is* a real, versioned Composer package, unlike raw WordPress itself —
unlike `wordpress-bridge`'s relationship to WP, this dependency can be
honest and first-class). Lives in `packages/taw-bridge/`, wired to both
`reactiph/reactiph` and the real local `taw/core` via Composer path
repositories for now — a local-machine convenience for developing the two
frameworks together, not yet a real VCS/Packagist reference (see
Consequences).

**`ReactiveMetaBlock extends MetaBlock`** is the one class this package
adds. `TAW\Core\Block\BlockLoader::scanDirectory()` auto-discovers a block
purely via `is_subclass_of($class, MetaBlock::class)` — since
`ReactiveMetaBlock` genuinely *is* a `MetaBlock` (real inheritance, not a
lookalike), **TAW's existing block auto-discovery, registry, and
asset-enqueue-fallback machinery all work with zero changes to `taw-core`
itself**. A Reactive block is authored exactly like any other TAW block:
its own folder, `registerMetaboxes()`, `getData()` — just with a third
abstract method, `componentClass()`, naming the Reactiph component it
renders and hydrates instead of an `index.php` PHP-include template.

**No new `BridgeInterface` implementation.** A TAW site is a real
WordPress site — `WordPressBridge`'s REST-route asset serving and RPC
dispatch (ADR 0019) apply completely unchanged. `ReactiveMetaBlock::render()`
composes a plain `WordPressBridge` instance internally just to call its
already-idempotent `enqueueRuntimeAssets()`. Introducing a `TawBridge
implements BridgeInterface` that would do nothing but delegate every
method to `WordPressBridge` was considered and rejected as pure
unnecessary indirection — the real integration work here is entirely at
the block-authoring layer, not the transport layer.

**The state-model mismatch is resolved by treating `MetaBlock` as an
adapter/factory, not a `BaseComponent`.** A `MetaBlock` instance is
long-lived — one per declared variation, created once at boot and
registered in `BlockRegistry` — with `render($postId)` called repeatedly
for different posts against data gathered fresh each time via `getData()`.
Reactiph's own model assumes the opposite: state lives on a single
instance's own properties. `ReactiveMetaBlock::render()` reconciles this
by constructing a **fresh** Reactiph component instance on every call,
mapping `getData($postId)`'s array onto its public properties
(`applyState()` — unlike `ReactiphShortcode`'s string-attribute coercion,
no type coercion is attempted by default, since TAW's metabox engine
already returns correctly-typed values), and setting
`hydrationId = "{$this->getId()}-{$postId}"` for uniqueness across
multiple posts/instances on one page.

**Dynamically-transpiled component JS is inlined, not routed through
Vite**, for now. `ComponentTranspiler` generates JS per class at render
time; Vite's manifest-based production asset resolution expects real
files that existed at build time. Reconciling those two is real, separate
scope — deferred, matching how the current shared runtime files
(`php-runtime.js`/`hydrate.js`) are still served via `WordPressBridge`'s
REST routes rather than through TAW's Vite pipeline either, even though
TAW's own static-asset serving is genuinely more capable than that
REST-route workaround. `ReactiveMetaBlock::emitComponentJs()` mirrors
`ReactiphShortcode`'s own per-class de-duplication (a static cache,
reset only for test isolation, never in production) so the same
component class rendered by multiple block instances on one page doesn't
re-emit its `window.ReactiphComponents[X] = {...}` assignment redundantly.

## Alternatives considered

- **A `TawBridge implements BridgeInterface` wrapping `WordPressBridge`.**
  Rejected as premature abstraction — nothing about asset serving or RPC
  dispatch actually differs for a TAW site versus any other WordPress
  site; introducing the wrapper class would add indirection with no
  behavioral difference behind it.
- **`ReactiveMetaBlock` itself holding component state** (extending
  `BaseComponent` somehow, or literally being one). Not viable —
  `MetaBlock`'s own long-lived, `$postId`-parameterized lifecycle is
  fundamentally incompatible with a single instance owning per-render
  state; PHP has no multiple inheritance either way. A fresh component
  instance per `render()` call is the only design that respects both
  models' actual lifecycles.
- **Pre-transpiling component JS into a real static file Vite could
  bundle**, solving the dynamic-JS-vs-static-pipeline mismatch properly
  now. Rejected for this pass: real new tooling work (a build step
  generating `.js` files from component classes ahead of time), better
  scoped once Reactiph has any CLI/build tooling at all (Part 8), not
  invented as a side effect of this decision.
- **Testing against a hand-rolled stand-in for `MetaBlock`/`BaseBlock`**
  instead of the real `taw/core` classes (mirroring how
  `wordpress-bridge` stubs bare WordPress functions rather than needing a
  real WP install). Rejected: the whole point of this package is that
  `ReactiveMetaBlock` genuinely, correctly extends the real `MetaBlock` —
  a hand-stubbed lookalike could drift from the real class's shape
  silently and prove nothing. `taw/core` was pulled in as a real
  dev-time dependency via a path repository instead, and only bare
  WordPress *functions* (the smaller, genuinely-external surface both
  `taw/core` and this package touch) are stubbed, the same way
  `wordpress-bridge` already does.

## Consequences

- **A real, non-obvious pitfall surfaced and is now documented**
  (`docs/gotchas.md`): every `taw/core` file guards with
  `if (!defined('ABSPATH')) { exit; }`, and merely *autoloading* one
  without `ABSPATH` defined first presented as an indefinite hang, not a
  clean, fast failure. `tests/bootstrap.php` defines a placeholder
  `ABSPATH` before anything else loads.
- **The path repository pointing at the real local `taw/core`
  (`../../../TAW/taw-core`) is a local-machine convenience**, not a
  portable dependency declaration — this only resolves on a machine
  where both repos happen to sit as sibling directories under the same
  parent. A real VCS or Packagist reference is needed before this package
  could be installed by anyone else, same caveat `taw-theme`'s own
  quickstart already carries for its own VCS-repository installation.
- **A second live-verification debt, alongside Part 7's.** Everything
  here is proven against the real `taw/core` *classes* (genuine
  inheritance, genuine method signatures) but hand-rolled WordPress
  *function* stubs — not a real running WordPress + TAW site. Live
  verification (does `BlockLoader` really auto-discover
  `examples/taw-block/Counter/` when copied into a real theme's `Blocks/`
  directory; does a real click really patch the DOM the same way Part
  5/6/7's demos already proved) is deliberately deferred, tracked
  alongside the still-pending Part 7 WordPress check in `docs/STATUS.md`
  — both could reasonably happen in the same session against the same
  Local by Flywheel TAW site.
- Component JS payload de-duplication is now implemented independently,
  near-identically, in two places (`ReactiphShortcode` in
  `wordpress-bridge`, `ReactiveMetaBlock` here) — small enough that
  extracting a shared helper isn't clearly worth it yet, but worth
  revisiting if a third integration point needs the same pattern.
