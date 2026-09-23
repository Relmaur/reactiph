# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 4 — PHP→JS transpiler MVP: slices 1-3 done and verified. Stdlib
builtins are the only piece of the plan's original subset left.**

Per the plan's "timeboxed spike, confirm scope is holding" instruction,
checked in with the user at two points: once after slice 1 (small core
subset — they said commit and keep expanding), and again before starting
arrays specifically, since PHP's array/JS Array-Object duality was flagged
as a materially different kind of design decision than what came before
(they confirmed the recommended approach before it was implemented).

- `Transpiler\PhpToJs::transpileMethod()` now covers everything from the
  plan's original subset except stdlib builtins: property/local-variable/
  array-element reads and writes, `$this->method()` calls (positional
  args only), arithmetic, increment/decrement, strict comparison,
  relational comparison, boolean ops, string concatenation,
  `if`/`elseif`/`else`, `while`, `for`, `foreach`, array literals, and
  `return`.
- **Array duality resolved** (ADR 0013): a sequential-key array literal
  compiles to a JS Array; a purely string-keyed one to a JS Object; mixed,
  gapped, or computed keys are rejected at compile time rather than
  guessed at. Array access (read/write with an explicit key) compiles
  uniformly via JS bracket notation for either shape. Array *append*
  (`$arr[] = ...`) is rejected outright — PHP's append semantics need
  runtime knowledge of the array's shape that the compiler doesn't have.
- **`foreach` compiles to a plain inline `for...of` loop**, never a
  callback — same reasoning as the `let`-hoisting design in slice 1 (ADR
  0011): a callback would silently break PHP's function-scoping for any
  variable assigned inside the loop body and read after it. A new runtime
  helper, `__phpEntries()`, bridges the Array/Object duality for
  iteration.
- Parity suite (ADR 0006) grew from 20 to 27 cases, covering both array
  representations, read/write access, and both `foreach` forms
  (value-only and key+value). No new correctness bugs surfaced this
  slice — clean implementation, unlike slice 2's test-harness ordering bug.
- 96 PHPUnit tests passing (52 in `tests/Transpiler/`). PHPStan (level 8)
  and PHP-CS-Fixer both clean.
- Committed through slice 2 (`50e653e`); slice 3 (arrays/foreach) not yet
  committed as of this status update.

## Next up

**Part 4 continued — stdlib builtins** (`Transpiler\Stdlib`, not started):
~15-20 common PHP functions (candidates: `count`, `strlen`, `implode`,
`explode`, `in_array`, `array_map`, `array_filter`, `sprintf`,
`str_replace`, `trim`, `strtolower`, `strtoupper`, `array_key_exists`,
`is_null`/`isset`-equivalent). Note from ADR 0013: several of these
(`count`, `array_map`, `array_filter`, ...) need to account for both
possible JS array representations, the same way `__phpEntries()` already
does for `foreach` — not a simple 1:1 JS function mapping for every
builtin. This is the last piece of the plan's original transpiler subset.

Once that's done, Part 4 as originally scoped is complete — worth a final
report back to the user before moving to **Part 5 — Reactive client
runtime**, which replaces Part 3's hand-written `Counter` stub with real
transpiled component output, wrapped in a JS `Proxy` for reactivity, with
`(click)="method"` template bindings (not yet implemented in `Parser` —
currently only interpolation/attribute expressions exist) and a minimal
scoped DOM patch step.

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
  deliberately until Part 5 needs real JS build tooling.
- `(click)="method"` event-binding template syntax (shown in the plan's
  own example and in `CLAUDE.md`'s opening description) doesn't exist yet
  in `Template\Parser` — needed for Part 5, not designed yet.
