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
Parts 4-6 specifically, a live check is additionally required — a
Node-executed parity assertion (transpiler) or an actual browser load
(hydration/reactivity). Unit tests alone don't count once a browser or
Node runtime is in the loop.

## Working in this repo

```
composer install         # install dependencies
composer test              # run the test suite (vendor/bin/phpunit)
composer analyse           # PHPStan, level 8, src/ only
composer cs-check          # PHP-CS-Fixer, dry-run
composer cs-fix            # PHP-CS-Fixer, apply fixes
composer check              # test + analyse + cs-check together
php examples/render.php   # run Part 1's manual smoke test
```

CI (`.github/workflows/ci.yml`) runs `test`, `analyse`, and `cs-check` on
push/PR against PHP 8.1 and 8.4. A part isn't done until `composer check`
passes clean, in addition to any live check the part requires (see Build
order above).

Before recommending or reusing something from an ADR or `docs/gotchas.md`,
verify it still matches current code — both are point-in-time records, not
guarantees the referenced code hasn't since changed.

## Self-improvement loop

When you hit something non-obvious (a bug whose fix wasn't where you'd
expect, a footgun in a library, a design constraint that isn't visible from
reading the code) add it to `docs/gotchas.md`. When you make or discover a
real architectural decision — not "how I implemented this function," but
"why the project does X instead of Y" — write an ADR in `docs/adr/`
(`docs/adr/template.md` has the shape). Update `docs/STATUS.md` at the end
of every part so the next session starts warm instead of re-deriving state
from git log.
