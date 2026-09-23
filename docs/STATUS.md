# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 4 — PHP→JS transpiler MVP: done, verified, everything from the
plan's original subset covered.**

Per the plan's "timeboxed spike, confirm scope is holding" instruction,
checked in with the user twice: after slice 1 (small core subset — they
said commit and keep expanding), and again specifically before starting
arrays, since the PHP-array/JS-Array-Object duality was a materially
different kind of design decision than what came before (confirmed the
approach before implementing). Slices 2 (writes/calls/loops), 3
(arrays/foreach), and the stdlib builtins slice were built without
further checkpoints, per that approval, each with its own ADR.

- `Transpiler\PhpToJs::transpileMethod()` covers the plan's full original
  subset: property/local-variable/array-element reads and writes,
  `$this->method()` calls (positional args only), ten allow-listed stdlib
  builtins (`count`, `strlen`, `array_key_exists`, `implode`, `explode`,
  `trim`, `strtolower`, `strtoupper`, `str_replace`, `in_array` with
  required `strict: true`), arithmetic, increment/decrement, strict
  comparison, relational comparison, boolean ops, string concatenation,
  `if`/`elseif`/`else`, `while`, `for`, `foreach`, array literals, and
  `return`.
- **A real, already-shipped bug was found and fixed mid-part**: slice 1's
  string concatenation used JS's native `String()`, which stringifies
  `true`/`false` as `"true"`/`"false"` — PHP casts them to `"1"`/`""`.
  Shipped silently through slices 1-3 because the only concat parity case
  used a string operand, never a boolean. Fixed via a `__phpString()`
  runtime helper; see ADR 0014 and the "types actually exercised" gotcha.
- Every stdlib builtin was checked against real PHP/JS behavior before
  being added, not assumed safe from its apparent JS equivalent —
  `strlen()` (bytes vs. UTF-16 code units), `trim()` (PHP's fixed ASCII
  charset vs. JS's broader Unicode one), `strtolower`/`strtoupper`
  (PHP's ASCII-only default vs. JS's Unicode-aware native methods) all
  have real, confirmed divergences the shims specifically work around.
  `array_map`/`array_filter` (need closures — unimplemented) and
  `sprintf` (needs its own format-parser) are deliberately not
  implemented rather than half-built; see ADR 0015.
- Parity suite (ADR 0006) grew to 83 cases across `tests/Transpiler/`,
  covering every supported construct plus the specific divergence cases
  (truthiness, boolean stringification, array shape, byte-length,
  ASCII-only case conversion) that make the transpiler trustworthy rather
  than just plausible-looking.
- 117 PHPUnit tests passing project-wide. PHPStan (level 8) and
  PHP-CS-Fixer both clean.
- Committed and pushed through the slice-2/3 boundary (`bb30c82`) and the
  ADR 0014 bugfix (`9bca9bc`); the stdlib builtins slice (ADR 0015) is
  not yet committed as of this status update.

## Next up

**Part 5 — Reactive client runtime**, pending user go-ahead (per the
working agreement: report back at part boundaries, don't proceed
automatically). Replaces Part 3's hand-written `Counter` stub with real
transpiled component output. Needs, per the original plan: components
wrapped in a JS `Proxy` to detect state writes; `(click)="method"`
template bindings wired from compiled template metadata to real DOM
listeners (this template syntax **doesn't exist yet** in `Template\Parser`
— currently only `{$expr}` interpolation and whole-value expression
attributes are supported, no event-binding attribute syntax at all); and
a minimal scoped DOM patch step (text/attribute/child-list diff), no full
vdom needed at this scale.

A concrete open question for Part 5 to resolve, not yet designed: how a
full component's *multiple* transpiled methods get assembled onto one JS
object (each `PhpToJs::transpileMethod()` call handles exactly one method
in isolation — see ADR 0012's "Consequences" on this), and how nested
components in a tree each get their own `hydrationId` (ADR 0010's
single-demo-component limitation).

## Remaining parts (unstarted)

6. Bridge abstraction + `DefaultBridge`.
7. `reactiph/wordpress-bridge` package.
8. CLI/dev tooling + docs.

## Open threads / not yet decided

- Template syntax has no control-flow directive (`@foreach`/`@if` or
  similar) for dynamically rendering a variable-length list of children —
  the plan's Part 2 scope didn't call for it, so the Part 2 Blog example
  hardcodes a fixed number of `<Thumbnail />` tags rather than looping
  over an array. Not designed yet.
- Only one, unnamed default slot exists per component (ADR 0008) — no
  named/multiple slots.
- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently a silent no-op (ADR 0010) — needs a real
  decision (most likely: enforce single-root globally) once Part 5
  hydrates more than one component.
- No allow-list yet for which public properties should actually reach the
  client as hydration state vs. stay server-only (ADR 0010).
- `packages/runtime-js` has no `package.json`/npm tooling yet — deferred
  deliberately until Part 5 needs real JS build tooling.
- Closures are entirely unimplemented in the transpiler — blocks
  `array_map`/`array_filter` (ADR 0015) and possibly other Part 5 needs;
  revisit together if Part 5 needs closures for its own reasons.
- How multiple transpiled methods of one component get assembled onto a
  single JS object, and how nested components each get a `hydrationId` —
  both open, called out above under "Next up."
