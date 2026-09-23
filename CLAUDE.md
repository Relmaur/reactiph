# Reactiph

A Viewi-style ([github.com/viewi/viewi](https://github.com/viewi/viewi))
reactive PHP component framework: `<CustomTag prop="{$expr}" (click)="method" />`
template syntax, server-rendered, then made reactive client-side via a real
PHP→JS transpiler — not a simpler server-round-trip model (that alternative
was explicitly considered and rejected; see `docs/adr/0001-*.md`).

Read `docs/STATUS.md` first — it says exactly where the build currently
stands and what's next. This file (`CLAUDE.md`) covers things that don't
change often: architecture, decisions, and how to work in this repo.

## Working agreement

**Build one Part at a time, and stop for explicit user go-ahead between
parts.** Do not proceed automatically into the next part after finishing
and verifying one — summarize what was built and wait. This was stated
directly by the user during planning; Part 4 in particular is called out
as high-risk and needs to be timeboxed and checked before continuing.

This is a standalone repo, not a TAW submodule — TAW's git/commit/bump/push
workflow rules do not apply here (see `docs/adr/0007-*.md`).

## Architecture

```
reactiph/
├── src/
│   ├── Template/     Parser + Compiler (template syntax → PHP render closures)
│   ├── Component/    BaseComponent, lifecycle hooks, component registry
│   ├── Runtime/       SSR renderer, hydration-manifest serializer
│   ├── Transpiler/    PHP→JS transpiler (nikic/php-parser-based) + stdlib shim
│   └── Bridge/         BridgeInterface + DefaultBridge (asset/RPC abstraction)
├── packages/
│   └── runtime-js/     Client runtime: hydration, event delegation, marker-based DOM patching
├── bin/reactiph         CLI: compile, watch, build
├── docs/
│   ├── adr/             Architecture decision records — the "why" behind locked-in choices
│   ├── gotchas.md        Running log of non-obvious pitfalls; check before re-debugging something
│   └── STATUS.md         Current build progress — read this first
├── examples/            Runnable manual smoke-test scripts, one per part where useful
└── tests/
```

Full rationale for each design decision below lives in `docs/adr/` — this
section is the short version.

- **Full PHP→JS transpiler**, not a server-round-trip model. (ADR 0001)
- **`nikic/php-parser`** for the transpiler, not a hand-rolled PHP parser.
  (ADR 0002)
- **Explicit allow-lists**: the transpiler's supported PHP subset and its
  stdlib shim are documented allow-lists. Unsupported constructs are a
  compile-time error, never silently-wrong JS. (ADR 0003)
- **`BridgeInterface`** is the seam between the framework-agnostic core and
  any host framework — core has zero WordPress dependency; a separate
  `reactiph/wordpress-bridge` package implements the same interface.
  (ADR 0004)
- **Hydration is proven before the transpiler exists**: Part 3 builds the
  SSR→hydrate wiring against a hand-written JS stub; Part 4 builds the real
  transpiler; Part 5 swaps the stub for real transpiled output. This keeps
  hydration-protocol bugs and transpiler bugs from being debugged at the
  same time. (ADR 0005)
- **Parity-test the transpiler** from day one of Part 4: the same PHP
  method run through real PHP and through transpiled-JS-in-Node must
  produce identical output. (ADR 0006)
- **PascalCase tag names are components**, resolved at render time through
  a static `ComponentRegistry`; a custom tag's children render in the
  *parent's* scope into the child's `slot` property. (ADR 0008)
- **Every exception implements `ReactiphException`**: a stable `code()`,
  an optional `hint()`, structured `context()`, and JSON-serializable —
  built only via named static constructors (e.g.
  `ParseException::missingClosingTag(...)`), never `new Xyz($message)`.
  This is a standing convention for every future part, not a one-time
  deliverable. (ADR 0009)
- **A component's root element gets `data-reactiph-id` when
  `$hydrationId` is set**, and its state is serialized into a
  `#reactiph-hydration` `<script type="application/json">` island — the
  manifest a hydration client reads to find and attach to server-rendered
  DOM. Only works when the template's root is a single literal HTML tag;
  silently inert otherwise (a known limitation, not yet an error). (ADR
  0010)
- **The transpiler now covers everything from the plan's original subset**
  (ADR 0011 → 0015): reads/writes of properties, local variables, and
  array elements; method calls (positional args only); ten allow-listed
  stdlib builtins; arithmetic, increment/decrement, strict comparison,
  boolean ops, string concat; `if`/`elseif`/`else`, `while`, `for`,
  `foreach`; and array literals. PHP and JS diverge, in real rather than
  theoretical ways, on more of this than seems obvious at first — e.g. the
  string `"0"` is falsy in PHP but truthy in JS; PHP casts `true`/`false`
  to `"1"`/`""` when stringified, not `"true"`/`"false"`; `strlen()`
  counts bytes, not JS's UTF-16 code units; `strtolower()`/`trim()` use a
  fixed ASCII rule set, not JS's Unicode-aware ones. Transpiled code never
  relies on the "obvious" native JS behavior for any of these — each has
  its own runtime shim in `packages/runtime-js/php-runtime.js`
  (`__phpBool`, `__phpString`, `__phpStrlen`, `__phpTrim`, ...), and each
  shim's necessity was verified empirically (`php -r` vs `node -e`) before
  trusting it, not assumed. Loose comparison (`==`/`!=`, including
  `in_array()` without `strict: true`) is rejected outright rather than
  approximated. A PHP array literal compiles to a JS Array
  (sequential-key) or a JS Object (purely string-keyed) — mixed or gapped
  keys are rejected, not guessed at; `foreach` compiles to a plain inline
  loop (never a callback) so PHP's function-scoping is preserved. One of
  these shims (`__phpString`) exists because a real bug shipped in slice 1
  and stayed shipped for two more slices before being caught — see ADR
  0014 and the "types actually exercised" entry in `docs/gotchas.md`.
  (ADR 0011-0015)
- **`(click)="method"` compiles to a real `data-reactiph-on-click="method"`
  DOM attribute**, on whichever tag declares it (root or not) — no
  manifest bookkeeping needed, since the binding is fully recoverable
  from the rendered DOM. The client runtime (`packages/runtime-js/hydrate.js`,
  replacing Part 3's throwaway stub) attaches one delegated listener per
  hydration root rather than one per bound element.
  `Transpiler\ComponentTranspiler` assembles a component class's own
  declared methods (excluding inherited/magic ones) into one JS
  definition registered on `window.ReactiphComponents`, shared across
  every instance of that class on the page. Live-verified in a browser:
  clicking a bound button runs the *real* transpiled method and mutates
  state correctly across repeated clicks. (ADR 0016)
- **After a bound method runs, the DOM is patched via SSR comment
  markers** — not a second template→JS compiler, not a `Proxy`. Every
  text-position `{$expr}` compiles to an `<!--rN--><!--/rN-->` marker pair
  (emitted only when `hydrationId` is set, so a never-hydrated render pays
  no byte cost) plus a recorded raw-PHP-source entry
  (`Template\CompiledTemplate::$expressions`); `ComponentTranspiler`
  transpiles each into a JS thunk (`window.ReactiphComponents[class].expressions`)
  via a new `PhpToJs::transpileExpression()`. `hydrate.js` recomputes and
  patches every marker for a component right after its bound method
  returns — the same explicit checkpoint the delegated click listener
  already has, not automatic write-detection. Scoped to text-node content
  only (attribute and structural patching are unbuilt — no template
  control-flow syntax exists yet to need the latter). Live-verified: a
  bound button's visible count patches 3 → 4 → 5 across real clicks,
  matching the transpiled method's real state. (ADR 0017)
- **`BridgeInterface`** (`assetUrl()`, `rpcEndpointUrl()`, `handleRpc()`)
  is the seam ADR 0004 named, now implemented. `handleRpc()` takes/returns
  plain arrays — no HTTP-framework type — and every implementation is
  expected to delegate to `Bridge\RpcHandler`, which does the real
  dispatch work once (validate payload, resolve component + method via
  `Component\OwnMethods`, apply state, call the real PHP method, return
  new state) so a future `reactiph/wordpress-bridge` only has to write its
  own request/response marshaling, not reimplement validation.
  **No mechanism yet marks a method as server-only vs. client-transpiled**
  (a real fork, decided with the user) — `ComponentTranspiler` and
  `RpcHandler` treat the exact same method set (`OwnMethods::of()`)
  RPC-callable and client-transpiled alike; a method needing real
  server-side work (file I/O, eventually a DB call) must stay on a
  component that's never passed to `ComponentTranspiler` at all.
  `DefaultBridge` targets PHP's built-in server; `examples/bridge-server.php`
  is a real running front controller (`php -S`), live-verified via curl
  (asset routes, RPC round-trip with genuine server-side persistence
  across requests, error handling) and a real browser (Counter's Part 5
  DOM patching now loading its runtime JS from actual Bridge-served HTTP
  responses; a second demo component, `Guestbook`, round-tripping a
  file-backed counter through RPC with no client transpilation involved
  at all). (ADR 0018)
- **`packages/wordpress-bridge/`** is `reactiph/wordpress-bridge` — a
  separate Composer package (own `composer.json`/`vendor`/tests), wired to
  core via a Composer path repository rather than a separate git repo.
  `WordPressBridge::assetUrl()` returns a WP REST URL
  (`reactiph/v1/assets/{name}`), not a raw `vendor/` filesystem path —
  many real WP hosts block direct `vendor/` web access, so
  `wp_enqueue_script()` is still used (per ADR 0004) but pointed at that
  REST URL. The asset route serves raw bytes via a `rest_pre_serve_request`
  filter (a tagged-response pattern, not `exit()` inside the callback —
  the first draft did that and was untestable/uncomposable, fixed before
  shipping). RPC auth is a standard `wp_rest` nonce, delivered to the
  client via `wp_localize_script()`; `Bridge\RpcHandler` itself (core)
  needed zero changes. `ReactiphShortcode` (`[reactiph component="..."]`)
  is the Part 7 baseline integration point — a Gutenberg block is still
  unbuilt. Tested against hand-rolled WP function stubs
  (`tests/bootstrap.php`, also PHPStan's `scanFiles` source), not a real
  WP install — **live verification against a real WordPress site is
  deliberately deferred**, not done; `examples/wordpress-plugin/` is the
  ready-to-activate artifact for when that happens. (ADR 0019)
- **Folder-based components** are optional sugar on top of the existing
  model, not a replacement — `BaseComponent::template()` is no longer
  abstract; a component that doesn't override it loads its markup from a
  sibling `{ShortClassName}.reactiph.html` file instead, resolved via
  reflection on the component's own class file (never the caller's working
  directory). `Component\ComponentDiscovery::registerDirectory()`
  auto-registers every component under a directory by its short class
  name — it statically parses each `.php` file with `nikic/php-parser` to
  learn its declared class **without including or evaluating it**, then
  loads it through the ordinary Composer autoloader, so a discovered class
  still has to resolve through normal PSR-4 rules. A request-time scan,
  deliberately uncached for now (Part 8 follow-on). Styles are explicitly
  out of scope — no asset pipeline exists yet to serve them. Deliberately
  called "components," never "blocks," to avoid colliding with the
  actual planned WordPress Gutenberg *block* integration. (ADR 0020)
- **`packages/taw-bridge/`** is `reactiph/taw-bridge` — a TAW-specific
  integration, not another generic-WordPress one, requested to replace
  the shortcode as the real embedding mechanism for the user's own TAW
  projects. Its one class, `ReactiveMetaBlock`, extends TAW's real
  `TAW\Core\Block\MetaBlock` — since TAW's own `BlockLoader` auto-discovers
  purely via `is_subclass_of($class, MetaBlock::class)`, this needs **zero
  changes to `taw-core`** to work. No new `BridgeInterface` implementation
  exists for TAW specifically — a TAW site is a real WordPress site, so
  `WordPressBridge`'s asset/RPC mechanics (ADR 0019) apply unchanged;
  `ReactiveMetaBlock` just composes a `WordPressBridge` internally for
  `enqueueRuntimeAssets()`. A `MetaBlock` instance is long-lived
  (one per variation, `render($postId)` called repeatedly for different
  posts) which doesn't match Reactiph's own instance-property state model —
  `render()` resolves this by constructing a **fresh** Reactiph component
  instance on every call, mapping `getData($postId)`'s array onto its
  public properties. Tested against the **real** `taw/core` classes (a
  path-repo dev dependency on `~/Documents/TAW/taw-core`), not a
  hand-stubbed lookalike — only bare WordPress functions are stubbed, the
  same smaller surface `wordpress-bridge` already stubs. A real, non-obvious
  pitfall was hit and documented: every `taw/core` file guards with
  `if (!defined('ABSPATH')) { exit; }`, and merely autoloading one without
  `ABSPATH` defined first presented as an indefinite hang, not a clean
  failure (`docs/gotchas.md`). **Live-verified end to end against a real
  TAW site** (2026-09-23): `BlockLoader` auto-discovery, SSR markup,
  hydration, both REST asset routes, and real-browser click-to-increment
  DOM patching all confirmed with no runtime bugs — only the one
  `registerRoutes()`-wiring gap already predicted below, resolved
  theme-side. An actual RPC round-trip against a live site is still
  unverified (the demo component never needs one); see `docs/STATUS.md`.
  (ADR 0021)
- **`bin/reactiph`** — the real CLI, three subcommands: `compile
  <ComponentClass>` (transpile one component, print its JS — a fast
  single-component check and a CI-friendly way to catch a real
  `ReactiphException` before it ships), `build <source-dir> <output-dir>`
  (discover every component under a directory and transpile each into a
  static `<ShortClassName>.js` file plus a `manifest.json` — the concrete
  fix ADR 0021 deferred for hosts needing a real static file instead of
  request-time transpilation), and `watch <source-dir> <output-dir>`
  (reruns `build` whenever a `.php`/`.reactiph.html` file's mtime changes,
  polling rather than depending on a native filesystem-events extension).
  No config file and no console-framework dependency — three positional
  arguments per subcommand cover every real scenario so far; revisit both
  if that changes. `Application` writes to injected stdout/stderr streams
  for testability, but `bin/reactiph` itself is also exercised as a real
  process invocation (`proc_open`, not just an in-process call) since a
  stream substitution doesn't prove the shebang/argv/autoload-bootstrap
  wiring actually works. (ADR 0022)

## Build order

Each part is a self-contained implementation slice, built one at a time,
verified, with explicit go-ahead before the next.

1. Scaffold + SSR-only templating.
2. Component tree: custom tags, props, nesting, slots (still pure SSR).
3. Hydration payload + client bootstrap (hand-written JS stub — transpiler-free).
4. PHP→JS transpiler MVP. **Highest risk — timeboxed spike, confirm scope before continuing.**
5. Reactive client runtime (replaces the Part 3 stub with real transpiled components).
6. Bridge abstraction + `DefaultBridge` (framework-agnostic, end-to-end example).
7. `reactiph/wordpress-bridge` package.
8. CLI/dev tooling (watch mode, production build) + docs. **Done** — see
   ADR 0022. Docs half covered separately by the `reactiph-docs` site.

**Verification per part:** PHPUnit passing for anything server-side. For
Parts 3-6 specifically, a live check is additionally required — a
Node-executed parity assertion (transpiler) or an actual browser load
(hydration/reactivity). Unit tests alone don't count once a browser or
Node runtime is in the loop. (Part 3's live check needs an isolated
browser, not the shared `chrome-devtools-mcp` profile — see the "Part 3 —
Hydration" entry in `docs/gotchas.md` before assuming that tool just
works.)

## Working in this repo

```
composer install         # install dependencies
composer test              # run the test suite (vendor/bin/phpunit)
composer analyse           # PHPStan, level 8, src/ only
composer cs-check          # PHP-CS-Fixer, dry-run
composer cs-fix            # PHP-CS-Fixer, apply fixes
composer check              # test + analyse + cs-check together
php examples/render.php    # run Part 1's manual smoke test
php examples/blog.php      # run Part 2's manual smoke test
php examples/errors.php    # run the exception-foundation smoke test
php examples/hydrate.php   # generate examples/hydrate-output.html (Parts 3 & 5) — open it in a browser
php -S localhost:8080 examples/bridge-server.php   # Part 6 end-to-end Bridge demo — open http://localhost:8080/
php examples/folder-components.php   # folder-based component discovery + sibling-file template demo (ADR 0020)
php bin/reactiph compile ReactiphExamples\\Greeting   # transpile one component, print its JS (ADR 0022)
php bin/reactiph build examples/folder-based /tmp/reactiph-build   # transpile every component under a directory to static JS + a manifest
php bin/reactiph watch examples/folder-based /tmp/reactiph-build   # rebuild whenever a component file changes
```

`packages/wordpress-bridge/` and `examples/wordpress-plugin/` are their
own separate Composer packages (own `composer.json`/`vendor`) — `cd` into
either and run the same `composer test`/`analyse`/`cs-check`/`check`
scripts there, not from the repo root.

CI (`.github/workflows/ci.yml`) runs `test`, `analyse`, and `cs-check` on
push/PR against PHP 8.1 and 8.4, with Node also installed — the
Transpiler parity suite (ADR 0006) shells out to a real `node` binary, so
Node is a required test dependency from Part 4 onward, not just a local
convenience. A part isn't done until `composer check` passes clean, in
addition to any live check the part requires (see Build order above).

Before recommending or reusing something from an ADR or `docs/gotchas.md`,
verify it still matches current code — both are point-in-time records, not
guarantees the referenced code hasn't since changed.

## Self-improvement loop

When you hit something non-obvious (a bug whose fix wasn't where you'd
expect, a footgun in a library, a design constraint that isn't visible from
reading the code) add it to `docs/gotchas.md` — but only claims you've
actually verified; if you're not sure whether something is really the
cause, check before writing it down (see the `CarriesDiagnostics` entry in
that file for an example of a documented false lead). When you make or
discover a real architectural decision — not "how I implemented this
function," but "why the project does X instead of Y" — write an ADR in
`docs/adr/` (`docs/adr/template.md` has the shape). Update
`docs/STATUS.md` at the end of every part so the next session starts warm
instead of re-deriving state from git log.

Every new exception, in any part, implements `ReactiphException` (ADR
0009) from the start — that convention exists precisely so it doesn't need
rediscovering or retrofitting per part.
