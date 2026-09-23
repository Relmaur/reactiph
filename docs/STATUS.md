# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 4 — PHP→JS transpiler MVP: slice 1 done and verified; paused at
the agreed checkpoint, awaiting go-ahead to expand scope.**

Per the plan's "timeboxed spike, confirm scope is holding" instruction,
asked the user how they wanted the checkpoint to work rather than
assuming; they chose building a small core subset first, proving the
whole pipeline end-to-end, then pausing to report before continuing —
this entry is that report.

- `Transpiler\PhpToJs::transpileMethod()`: transpiles one PHP method's
  source to an equivalent JS function. Supports: property/local-variable
  reads, arithmetic (`+ - * / %`), **strict** comparison (`=== !==`),
  relational comparison (`< <= > >=`), boolean ops (`&& || !`), string
  concatenation (`.`), local variable assignment, `if`/`elseif`/`else`,
  `return`. Everything else (loops, arrays, `$this->method()` calls,
  property *writes*, loose `==`/`!=`) throws `TranspileException` — a
  compile-time error, never silently-wrong JS, per ADR 0003.
- Two real PHP/JS semantic gaps handled deliberately, not glossed over:
  PHP's `"0"`-string-is-falsy truthiness (JS disagrees) is replicated via
  a `__phpBool()` runtime shim (`packages/runtime-js/php-runtime.js`), and
  loose comparison is rejected outright rather than approximated. See ADR
  0011 for the full reasoning and the parity tests that specifically
  probe this.
- **Parity suite built from day one of this part, per ADR 0006** — not
  after: `ParityTest` runs the exact same method (read via Reflection
  from a real fixture class, never duplicated as a separate string) through
  real PHP and through transpiled-JS-in-Node, asserting identical results.
  14 cases, including the two truthiness-divergence cases that would fail
  without `__phpBool`. Test-only `NodeRunner` helper shells out to `node`.
- `nikic/php-parser` promoted from a transitive (PHPUnit) dependency to a
  direct one, per ADR 0002.
- CI (`.github/workflows/ci.yml`) now installs Node — the parity suite is
  a required test dependency from this part onward, not just local
  tooling.
- 71 PHPUnit tests passing (27 new: 13 unit tests on `PhpToJs`'s output
  shape and allow-list enforcement, 14 parity cases). PHPStan (level 8)
  and PHP-CS-Fixer both clean.
- Not yet committed — commits happen on explicit user request; this
  checkpoint report comes before that ask, in case the user wants changes
  before it's captured in a commit.

## Next up

**Part 4 continued — expand the transpiler's supported subset**, pending
user go-ahead. Deferred from slice 1: `for`/`foreach`/`while` loops,
array literals/access, `$this->method()` calls, property *writes*
(`$this->prop = ...`), and the ~15-20 builtin stdlib shims
(`Transpiler\Stdlib`, not yet started). Each addition should ship with a
parity test, per ADR 0006 — the truthiness-shim precedent in ADR 0011 is
the model for how to handle any other spot where PHP and JS quietly
disagree, rather than assuming a construct transpiles safely by default.

Once the transpiler's scope is where the plan wants it: **Part 5 —
Reactive client runtime**, replacing Part 3's hand-written `Counter` stub
with real transpiled component output, wrapped in a JS `Proxy` for
reactivity, with `(click)="method"` template bindings (not yet
implemented in `Parser` — currently only interpolation/attribute
expressions exist) and a minimal scoped DOM patch step.

## Remaining parts (unstarted)

6. Bridge abstraction + `DefaultBridge`.
7. `reactiph/wordpress-bridge` package.
8. CLI/dev tooling + docs.

## Open threads / not yet decided

- Template syntax has no control-flow directive (`@foreach`/`@if` or
  similar) for dynamically rendering a variable-length list of children —
  the plan's Part 2 scope didn't call for it, so the Part 2 Blog example
  hardcodes a fixed number of `<Thumbnail />` tags rather than looping
  over an array. Not designed yet; flag it if a later part needs dynamic
  lists in templates rather than inventing template control-flow syntax
  ad hoc when the need first comes up.
- Only one, unnamed default slot exists per component (ADR 0008) — no
  named/multiple slots.
- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently a silent no-op (ADR 0010) — no id gets
  emitted anywhere, and nothing on the PHP side catches the resulting
  payload/DOM mismatch. Fine for Part 3's one demo component; needs a real
  decision (most likely: enforce single-root globally, per ADR 0010's
  "Alternatives") once Part 5 hydrates more than one component.
- No allow-list yet for which public properties should actually reach the
  client as hydration state vs. stay server-only (ADR 0010) — everything
  public except `slot`/`hydrationId` is serialized today.
- `packages/runtime-js` has no `package.json`/npm tooling yet — deferred
  deliberately until Part 5 needs real JS build tooling; Part 3's stub and
  Part 4's runtime helper are single plain `.js` files with no
  dependencies.
- Transpiler slice 1 deliberately doesn't cover loops, arrays, method
  calls, or property writes (see "Next up") — a component method needing
  any of those can't be transpiled yet.
- `(click)="method"` event-binding template syntax (shown in the plan's
  own example and in `CLAUDE.md`'s opening description) doesn't exist yet
  in `Template\Parser` — needed for Part 5, not designed yet.
