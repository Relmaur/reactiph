# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current work

**`reactiph/taw-bridge` (ADR 0021) — built and tested against the real
`taw/core`, live verification deferred.** Not one of the original 8
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
- Not yet committed as of this status update.

## Next up

Three threads are live and unstarted, none with a user go-ahead yet for
which to pick up:

1. **Live verification against a real WordPress site** — Part 7's
   original ask, still pending. Local by Flywheel's TAW site app wasn't
   running last checked.
2. **Live verification of `ReactiveMetaBlock` against that same TAW
   site** — new from this work, naturally paired with (1) since it's the
   same Local site: copy `examples/taw-block/Counter/` into the site's
   active theme's `Blocks/` directory, confirm `BlockLoader` picks it up
   with no code changes, and confirm click-to-increment patches the DOM
   the same way Parts 5/6/7's demos already proved.
3. **Part 8 — CLI/dev tooling + docs.** Docs half now covered by the
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
- **Live verification hasn't happened for either WordPress integration**
  (ADR 0019, ADR 0021) — see "Next up".
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
