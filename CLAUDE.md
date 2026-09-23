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
│   └── runtime-js/     Client runtime: hydration, Proxy-based reactivity, DOM patching
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
8. CLI/dev tooling (watch mode, production build) + docs.

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
```

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
