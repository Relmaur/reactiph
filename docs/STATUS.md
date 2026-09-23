# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current work

**Part 8 — `bin/reactiph` CLI (ADR 0022). Done.** Three subcommands:
`compile <ComponentClass>` (transpile one component, print its JS),
`build <source-dir> <output-dir>` (discover every component under a
directory and transpile each into a static `<ShortClassName>.js` file
plus a `manifest.json`), and `watch <source-dir> <output-dir>` (reruns
`build` on file change, polling mtimes). This is the concrete fix ADR
0021 deferred — a host now has real static files + a manifest to point at
instead of request-time transpilation, though wiring any specific bridge
to prefer them is still separate, undone work. No config file, no
console-framework dependency. `ComponentDiscovery::classesInDirectory()`
is new public API (the same scan `registerDirectory()` already ran,
exposed as data). Tested at both the command level (injected
stdout/stderr streams) and as a real process invocation
(`tests/Cli/BinExecutableTest.php`, via `proc_open` against the actual
`bin/reactiph` executable) — 15 new tests, all passing; PHPStan (level 8)
and PHP-CS-Fixer clean. One real bug caught and fixed while writing tests
for `watch`: the mtime baseline was captured *after* the initial build
ran instead of before, which could silently absorb a change into the
baseline it's supposed to be compared against — see `docs/gotchas.md`.
Docs half of Part 8 already covered separately by the `reactiph-docs`
site; `reactiph-docs-46` pinged about this CLI specifically, not yet
confirmed back.

**All 8 original build-order parts are now done.** What's left is the one
still-open verification thread below, plus the "Open threads" list, which
are refinements/extensions rather than unbuilt parts.

**A `Guestbook` RPC-round-trip demo for WordPress/TAW is built and
live-verified** (`examples/wordpress-plugin/reactiph-demo.php`) — see
"Live verification results" below. This closes out the last open
verification debt; nothing is currently pending a live check.

**Monorepo-split CI is set up and confirmed working (ADR 0023).**
`.github/workflows/monorepo-split.yml` mirrors `packages/wordpress-bridge`
and `packages/taw-bridge` into their own public repos
(`github.com/Relmaur/reactiph-wordpress-bridge`,
`github.com/Relmaur/reactiph-taw-bridge`) on every push to `main` and
every tag, via `danharrin/monorepo-split-github-action` — this was the
actual answer to "how do we make `wordpress-bridge`/`taw-bridge`
installable the way `taw-theme` already installs `taw/core`" (a plain
`vcs` repository + version constraint, no local path repo). `reactiph`
itself (this repo) and both new split repos are public, since both split
packages depend on `reactiph/reactiph` — a private core package would
have undercut the whole point.

Getting this actually green took three real fixes, in order:
1. Both target repos started genuinely empty (zero commits) — the
   action's own "create the branch if it doesn't exist yet" logic tries
   to `git push` an unborn branch in that case, which fails
   (`src refspec main does not match any`, since there's no commit yet
   for the ref to point at). Fixed by seeding each with one manual empty
   commit on `main` before the first real run.
2. The `ACCESS_TOKEN` secret didn't actually exist yet on a first check
   (`gh api repos/.../actions/secrets` showed zero) even after the user
   believed it was added — resolved once actually saved as a repository
   secret (not a variable) on `reactiph` specifically.
3. The fine-grained PAT itself had the two repos selected under
   "Repository access" but **zero permissions actually added** — GitHub's
   fine-grained token UI lets you select repos and separately, easy to
   miss, requires an explicit "+ Add permissions" step per capability
   (here, `Contents: Read and write`); skipping it produces a token that
   can clone but gets a real `403` on push. The user also independently
   hit a real GitHub UI quirk worth knowing about: re-opening a saved
   fine-grained token for editing can show "Public repositories" as
   selected instead of the token's actual saved scope — worth explicitly
   re-checking "Only select repositories" is still correct before hitting
   Update, not trusting what the edit form shows on open.

Verified via `gh run watch` (both matrix jobs green) and by reading the
split repos' actual pushed contents back
(`gh api repos/.../contents/composer.json`) — both contain the real
package source, correct `"name"` fields (`reactiph/wordpress-bridge`,
`reactiph/taw-bridge`), not just "the workflow didn't error." Documented
in `docs/gotchas.md`.

**`taw-theme` itself switched over and re-verified, via `taw-85`.** Its
`composer.json`'s three `path` entries replaced with `vcs` entries against
`reactiph`/`reactiph-wordpress-bridge`/`reactiph-taw-bridge`, same
`minimum-stability: dev` kept (still needed — see "no real semver tags"
below). `composer update --with-all-dependencies` resolved and installed
cleanly from the three new GitHub repos (`vendor/reactiph/*` confirmed as
real installed directories, not symlinks — composer's own output said
"Downloading/Extracting," not "Symlinking from," which is what a `path`
repo install prints). Full re-verification against the live site after
the swap, everything still passing: `BlockRegistry` resolution, SSR/
hydration/asset routes, Counter's click-to-increment (3→4), and
`Guestbook`'s RPC round-trip (2→3, persisted across a reload) — a pure
distribution-mechanism change, no behavior differences. Committed in
`taw-theme` (`35f27c9`), not yet pushed.

One thing `taw-85` flagged: `packages/wordpress-bridge/composer.json` and
`packages/taw-bridge/composer.json` still carry their own local `path`
repository entries (`../..`, `../wordpress-bridge`) pointing at
filesystem paths that don't exist outside this monorepo, and those get
copied verbatim into the split repos. **Intentional, not a bug** — those
entries are what let each package's own `composer install`/`test` resolve
`reactiph/reactiph` locally for development inside this monorepo, and
Composer only ever reads a *root* project's `repositories` key, never a
dependency's — confirmed correct by `taw-85`'s own installed-directory
check. Worth knowing, not worth "fixing." See `docs/gotchas.md`.

This closes the loop `taw-85` was originally asked to check — ADR 0023 is
fully built, live-verified, and now actually adopted by its one real
consumer.

## `reactiph/taw-bridge` (ADR 0021)

Built, tested against the real `taw/core`, and live-verified end to end
against a real TAW site. Not one of the original 8
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
- **RPC round-trip — live-verified separately (2026-09-23)**, see the
  `Guestbook` section immediately below. This check didn't exercise it
  (`Counter::increment()` is entirely client-transpiled), which is why a
  second, dedicated check was built and run.
- Left in place on the TAW site (nothing committed/pushed there, nothing
  touched in this repo): the theme's path-repo `composer.json`/`lock`
  changes, `Blocks/Counter/`, the `rest_api_init` hook in
  `inc/customizations.php`, a new `page-reactiph-test.php` template, and
  a published test page. Cleanup/keep is the user's call on the TAW side.

### `Guestbook` RPC round-trip — live-verified (2026-09-23)

`examples/wordpress-plugin/reactiph-demo.php`'s `Guestbook` component +
`[reactiph_guestbook]` shortcode (mirroring Part 6's `Guestbook` exactly:
`sign()` does a WordPress options-table read/write, work outside the
transpiler's allow-listed subset, rendered SSR-only and never passed to
`ComponentTranspiler`) verified end to end by `taw-85` against the same
live TAW site, pasted into `inc/reactiph-guestbook.php` and required from
`inc/customizations.php` rather than activating the standalone plugin.

- Confirmed via `curl` before ever opening a browser: SSR-only, no
  transpiled JS anywhere in the page source for this component.
- Click Sign: a real `POST /wp-json/reactiph/v1/rpc` with an `X-WP-Nonce`
  header, 200, `{"state":{"signatureCount":1}}`, DOM updates. Second
  click: 1 → 2, response matches.
- **Reloaded the page**: SSR still showed "Signatures so far: 2" —
  `get_option`/`update_option` genuinely persisted server-side across a
  fresh request, not just client-side JS state.
- Zero console errors/warnings, zero 400/403s from the nonce check, at
  any point.
- **One anomaly encountered and run to ground, not a reactiph/wordpress-bridge bug**:
  a first attempt went 0 → 2 on a single click with only one network POST
  visible in the browser tool's own log. `taw-85` didn't take that at face
  value — isolated `Guestbook::sign()` via `wp eval` (correct, +1), the
  real REST route via `wp eval-file` + `rest_do_request()` (correct, +1),
  confirmed no duplicate `rest_api_init` registration, then reset the
  option and re-ran cleanly (correct: 0→1, 1→2, persisted after reload).
  Most likely explanation offered: a second, already-running Chrome
  instance under the shared browser-automation profile that couldn't be
  attached to for this site — possibly a concurrent hit against the same
  option. Recorded as an unresolved, unreproduced anomaly with a plausible
  but unconfirmed explanation, not a defect — see `docs/gotchas.md`.
- State left in place on the TAW site: `inc/reactiph-guestbook.php` (new),
  `inc/customizations.php` (require + shortcode registration added),
  `page-reactiph-test.php` (a `do_shortcode()` call added — this theme's
  pages are block-driven with no `the_content()` template to drop a
  shortcode into normally). `reactiph_guestbook_count` option is at `2` on
  the live site.

**This closes out every currently-open live-verification thread** — SSR,
hydration, asset-serving, client-side DOM patching, and now a genuine RPC
round-trip have all been proven against a real WordPress/TAW site.

## Next up

Nothing is currently pending a user go-ahead. All originally-planned work
(the 8 build-order parts, plus the TAW-bridge rethink and the RPC-round-trip
follow-up) is built and live-verified. Remaining work is entirely the
"Open threads" list below — extensions and refinements, not unfinished
builds.

## Remaining parts (unstarted)

None — all 8 original build-order parts are done (Part 8 — CLI/dev
tooling — shipped this session, ADR 0022). Remaining work lives in "Next
up" above and "Open threads" below instead.

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
- **Live verification of ADR 0021 (TAW), ADR 0019 (WordPress asset/SSR/
  hydration), and a real RPC round-trip are all done** — see "Live
  verification results" above. No live-verification thread is currently
  open.
- A Gutenberg block is still unbuilt (ADR 0004/0019).
- `ComponentDiscovery::registerDirectory()` is an uncached, per-request
  filesystem scan (ADR 0020) — `bin/reactiph build` (ADR 0022) can produce
  a static manifest now, but nothing at *runtime* reads it back instead of
  rescanning; that's a separate, still-undone wiring decision.
- Folder-based component styles have nowhere to be served from yet (ADR
  0020) — same underlying gap blocking real Vite integration for
  `taw-bridge`'s dynamic component JS (ADR 0021).
- **`bin/reactiph build`'s static output has no invalidation mechanism**
  (ADR 0022) — nothing detects or warns when a component's PHP has changed
  since the last `build`, so a stale `.js` file can silently drift from
  what the component would transpile to today. Previously impossible,
  since every render always re-transpiled live; now possible wherever a
  host is wired to prefer the static build output (nothing is, yet).
- No specific bridge is wired to consume `bin/reactiph build`'s
  `manifest.json` instead of transpiling at request time (ADR 0022) —
  `wordpress-bridge` and `taw-bridge` both still call
  `ComponentTranspiler` live, same as before this CLI existed.
- **The `taw-bridge` → `taw/core` path repository is a local-machine
  convenience** (ADR 0021) — not a portable dependency; needs a real
  VCS/Packagist reference before anyone else could install this package.
- Component-JS de-duplication logic now exists independently in both
  `ReactiphShortcode` and `ReactiveMetaBlock` (ADR 0021) — small enough
  to leave unshared for now, worth revisiting if a third integration
  point needs it too.
- **`reactiph/reactiph` has no real semver tags** — everything installs
  as `dev-main`, which is why `taw-theme` (and any future consumer) needs
  `"minimum-stability": "dev"` set globally rather than a clean version
  constraint. Tagging real releases would fix this and would also tag
  both split repos to match (the split workflow's tag-triggered step) —
  not done yet, separate follow-up from the split CI itself.
- **`reactiph-wordpress-bridge`/`reactiph-taw-bridge` are CI-managed
  mirrors** (ADR 0023) — a change made directly in either split repo
  would be silently overwritten by the next split run. Both repos'
  descriptions say so; nothing enforces it technically yet (e.g. branch
  protection).
