# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current work

**`reactiph/taw-bridge` (ADR 0021) — built, tested against the real
`taw/core`, and now live-verified end to end against a real TAW site.**
Not one of the original 8
build-order parts, and not an extension of `wordpress-bridge` — the user
explicitly rejected the shortcode as their real integration point and
asked to rethink WordPress support as a TAW-specific one instead,
brainstormed first (grounded in TAW's actual current source, not assumed
from docs) and confirmed before building.

- **`packages/taw-bridge/`** — new package, depends on both
  `reactiph/reactiph` and the real `taw/core` (a path-repo dev dependency
  on `~/Documents/TAW/taw-core`, since `taw/core` genuinely is a real,
  versioned Composer package, unlike raw WordPress).
- **`ReactiveMetaBlock extends MetaBlock`** — the one class this package
  adds. TAW's own `BlockLoader` auto-discovers purely via
  `is_subclass_of($class, MetaBlock::class)`, so this required **zero
  changes to taw-core**. Authored like any other TAW block (folder,
  `registerMetaboxes()`, `getData()`) plus one new abstract method,
  `componentClass()`.
- **No new `BridgeInterface` implementation** — a TAW site is a real
  WordPress site, so `WordPressBridge` (ADR 0019) applies unchanged;
  `ReactiveMetaBlock` composes one internally just for
  `enqueueRuntimeAssets()`. Considered and rejected: a `TawBridge`
  wrapper class that would only delegate to `WordPressBridge` — pure
  unneeded indirection.
- **State-model mismatch resolved via the adapter pattern**: a
  `MetaBlock` instance is long-lived (one per variation, `render($postId)`
  called repeatedly for different posts); Reactiph's own model assumes
  state on one instance's own properties. `render()` constructs a fresh
  Reactiph component instance every call, mapping `getData($postId)`'s
  array onto its public properties (`applyState()` — no coercion by
  default, unlike `ReactiphShortcode`, since TAW's metabox engine already
  returns correctly-typed values).
- **Dynamic component JS is inlined, not routed through Vite** — real,
  separate scope, deferred alongside the same unsolved problem for the
  shared runtime files (still served via `WordPressBridge`'s REST routes,
  not TAW's own static-asset pipeline, even though that pipeline is
  genuinely more capable). `emitComponentJs()` mirrors
  `ReactiphShortcode`'s per-class de-duplication.
- **Tested against the real `taw/core` classes**, not a hand-stubbed
  lookalike — only bare WordPress functions are stubbed (the same,
  smaller surface `wordpress-bridge` already stubs), so
  `ReactiveMetaBlock`'s real inheritance from the real `MetaBlock` is
  genuinely exercised, not assumed.
- **Real pitfall hit and documented** (`docs/gotchas.md`): every
  `taw/core` file guards with `if (!defined('ABSPATH')) { exit; }` —
  merely autoloading one without `ABSPATH` defined first presented as an
  indefinite hang, not a clean failure. Fixed in `tests/bootstrap.php`.
- **`examples/taw-block/Counter/`** (new) — a real TAW block folder
  (`Counter.php` + a Reactiph `CounterComponent` using ADR 0020's
  folder-based component convention, both sugar layers composing
  together) ready to drop into a real TAW theme's `Blocks/` directory for
  the deferred live check.
- 7 new tests (`packages/taw-bridge`). PHPStan (level 8) and PHP-CS-Fixer
  clean across all three packages (core, `wordpress-bridge`, `taw-bridge`).
- Committed and pushed (`96342c9`, `103ef37`).
- **Two real bugs caught by the `reactiph-docs` peer session while writing
  up ADR 0021, both fixed and pushed (`189ed7a`)**: `ReactiveMetaBlock`
  had silently dropped `MetaBlock::render()`'s visual-editor wrapper
  (`data-taw-block-section`), and `packages/taw-bridge/composer.json`'s
  description still claimed a `BridgeInterface` implementation that ADR
  0021 explicitly decided against. See `docs/gotchas.md`.
- **Live-verified end to end against a real TAW site**, via a peer
  session (`taw-85`) working directly in the TAW repo — see "Live
  verification results" below.

## Live verification results (2026-09-23)

`reactiph/taw-bridge` verified against a real, running TAW Local site by
`taw-85`. Full success — no bugs found in the runtime/transpiler/hydration
mechanics themselves; only the one wiring gap already flagged below.

- `composer require reactiph/taw-bridge` into a real `taw-theme` via path
  repositories worked, needing `--with-all-dependencies` (taw/core pins
  `nikic/php-parser` to `5.8.0`; taw-bridge needs `^5.9`) and
  `"minimum-stability": "dev"` on the theme's own root `composer.json`,
  since `reactiph/reactiph` only exists as `dev-main` for now.
- `examples/taw-block/Counter/` copied into `Blocks/Counter/` verbatim,
  zero changes needed. `BlockLoader` auto-discovered it with zero
  `taw-core` changes (`BlockRegistry::get('reactiph-counter')` resolved
  correctly).
- View-source showed exactly the expected SSR markup
  (`data-reactiph-id="reactiph-counter-53"`), the `#reactiph-hydration`
  manifest, and the inline `window.ReactiphComponents[...]` assignment.
- Both REST asset routes returned real 200s with correct bytes/content
  type (`php-runtime.js`, `hydrate.js`).
- A real browser (Playwright): clicking Increment patched the DOM twice,
  3 → 4 → 5, entirely client-side (no RPC round-trip needed for this
  component), no console errors.
- **One real gap, exactly as flagged in advance**: nothing wires
  `WordPressBridge::registerRoutes()` to `rest_api_init` — this package
  deliberately leaves that to the host (see `examples/taw-block/Counter/README.md`).
  `taw-85` added it theme-side; worth noting their specific theme's own
  convention put it in `inc/customizations.php`, not `functions.php`
  (that theme treats `functions.php` as framework-owned and
  blindly-overwritten) — a reminder that "wherever it boots" genuinely
  varies per theme, not just a hedge phrase.
- **Not exercised by this check**: an actual RPC round-trip against a
  live WordPress/TAW REST endpoint. `Counter::increment()` is entirely
  client-transpiled state, so this check never POSTed to
  `/wp-json/reactiph/v1/rpc` for real. `RpcHandler`'s live behavior behind
  a real `wp_rest` nonce is still only unit/integration-tested, not
  browser-verified against a component that genuinely needs a server
  round-trip (mirroring Part 6's `Guestbook` demo). Worth closing out
  alongside Part 7's own still-open live check below, since both need the
  same kind of component.
- Left in place on the TAW site (nothing committed/pushed there, nothing
  touched in this repo): the theme's path-repo `composer.json`/`lock`
  changes, `Blocks/Counter/`, the `rest_api_init` hook in
  `inc/customizations.php`, a new `page-reactiph-test.php` template, and
  a published test page. Cleanup/keep is the user's call on the TAW side.

## Next up

Two threads are live and unstarted, none with a user go-ahead yet for
which to pick up:

1. **A live RPC round-trip against a real WordPress/TAW site** — narrower
   than the original Part 7 ask now that the SSR/hydration/asset-serving
   mechanics are proven live (above); what's left specifically is a
   component whose method needs real server state (like Part 6's
   `Guestbook`) exercised through a real `wp_rest`-nonce-gated REST call,
   not just a client-transpiled one like `Counter`.
2. **Part 8 — CLI/dev tooling + docs.** Docs half now covered by the
   separate `reactiph-docs` site. The CLI itself doesn't exist yet — and
   is also where a real fix for the "dynamic component JS vs. Vite's
   static pipeline" mismatch would naturally live.

## Remaining parts (unstarted)

8. CLI/dev tooling + docs (docs half now substantially covered by the
   separate `reactiph-docs` site).

## Open threads / not yet decided

- Template syntax has no control-flow directive. Gates structural DOM
  patching (ADR 0017) and a Gutenberg block (ADR 0019).
- Only one, unnamed default slot exists per component (ADR 0008).
- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently a silent no-op (ADR 0010).
- No allow-list yet for which public properties should reach the client
  as hydration state vs. stay server-only (ADR 0010).
- `packages/runtime-js` has no `package.json`/npm tooling yet — blocks a
  Gutenberg block, folder-based component styles (ADR 0020), and real
  Vite integration for `taw-bridge`'s dynamic component JS (ADR 0021).
- Closures are unimplemented in the transpiler (ADR 0015).
- Event bindings only support `click` in practice (ADR 0016).
- DOM patching is scoped to text-node content only (ADR 0017).
- Nested-component hydration has a defensive boundary check but no real
  example/test exercises it yet.
- **No mechanism marks a component method as server-only vs.
  client-transpiled** (ADR 0018).
- **`RpcHandler` (core) has no authentication of its own** (ADR 0018) —
  resolved for WordPress/TAW specifically (a `wp_rest` nonce), not for
  `DefaultBridge`/a plain PHP app.
- **Live verification of ADR 0021 (TAW) is done** — see "Live verification
  results" above. That check also transitively proves ADR 0019's
  `WordPressBridge` asset/SSR/hydration mechanics against a real WordPress
  site (a TAW site is a real WordPress site), but not an actual RPC
  round-trip — see "Next up".
- A Gutenberg block is still unbuilt (ADR 0004/0019).
- `ComponentDiscovery::registerDirectory()` is an uncached, per-request
  filesystem scan (ADR 0020).
- Folder-based component styles have nowhere to be served from yet (ADR
  0020) — same underlying gap blocking real Vite integration for
  `taw-bridge`'s dynamic component JS (ADR 0021).
- **The `taw-bridge` → `taw/core` path repository is a local-machine
  convenience** (ADR 0021) — not a portable dependency; needs a real
  VCS/Packagist reference before anyone else could install this package.
- Component-JS de-duplication logic now exists independently in both
  `ReactiphShortcode` and `ReactiveMetaBlock` (ADR 0021) — small enough
  to leave unshared for now, worth revisiting if a third integration
  point needs it too.
