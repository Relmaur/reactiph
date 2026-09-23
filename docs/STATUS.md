# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 4 — PHP→JS transpiler MVP: slices 1-2 done and verified. Arrays,
`foreach`, and stdlib builtins are the only pieces of the plan's original
subset left.**

Per the plan's "timeboxed spike, confirm scope is holding" instruction,
asked the user how they wanted the checkpoint to work rather than
assuming; they chose small-slice-first. After slice 1 landed, they said to
commit it and keep expanding without another explicit stop-and-ask each
time — this entry reflects that continued work.

- `Transpiler\PhpToJs::transpileMethod()` now supports: reading or
  writing a property (`$this->prop`) or local variable, `$this->method()`
  calls with **positional arguments only**, arithmetic (`+ - * / %`),
  increment/decrement (`++ --`), **strict** comparison (`=== !==`),
  relational comparison (`< <= > >=`), boolean ops (`&& || !`), string
  concatenation (`.`), `if`/`elseif`/`else`, `while`, `for` (single
  expression per clause only), and `return`.
- Still rejected, with a clear compile-time `TranspileException`: arrays
  (literals and access), `foreach`, loose `==`/`!=`, named/variadic
  method-call arguments, multi-expression `for` clauses.
- PHP/JS semantic gaps handled deliberately (ADR 0011): PHP's
  `"0"`-string-is-falsy truthiness replicated via a `__phpBool()` runtime
  shim rather than JS's native truthiness; loose comparison rejected
  outright rather than approximated.
- Parity suite (ADR 0006) now covers property writes, method-to-method
  calls (`NodeRunner` extended to attach multiple transpiled methods onto
  one JS `this` context — see ADR 0012), `while` loops, and `for` loops,
  alongside slice 1's cases. 20 parity cases total. A real test-harness
  ordering bug surfaced along the way (JS-side state snapshotted *after*
  calling a mutating PHP method instead of before) — fixed; see
  `docs/gotchas.md`.
- 82 PHPUnit tests passing (38 in `tests/Transpiler/`). PHPStan (level 8)
  and PHP-CS-Fixer both clean.
- Committed and pushed to `origin/main` through slice 1
  (`df9bb96`); slice 2 (property writes/method calls/loops) not yet
  committed as of this status update.

## Next up

**Part 4 continued — arrays, `foreach`, and stdlib builtins.** This is
flagged (ADR 0012) as genuinely more complex than what's landed so far:
PHP arrays are simultaneously ordered lists and string-keyed maps, with no
single JS type covering both faithfully. Needs a real design decision —
likely JS `Array` for sequential-integer-key ("list") arrays and plain
`Object` for associative ones, rejecting mixed/gapped-key arrays outright
— plus a runtime `foreach` helper that dispatches between the two at
runtime, and its own parity tests. Given this is a materially different
kind of complexity than loops/calls/writes were, worth treating as its
own checkpoint before broader implementation, consistent with this part's
"highest risk, timeboxed spike" framing — not assumed to be a quick
follow-on.

After that: the ~15-20 builtin stdlib shims (`Transpiler\Stdlib`, not yet
started).

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
  deliberately until Part 5 needs real JS build tooling.
- How JS array-vs-object array representation gets decided is the
  explicit next design question (see "Next up") — not yet a locked-in
  decision, don't assume a shape for it.
- `(click)="method"` event-binding template syntax (shown in the plan's
  own example and in `CLAUDE.md`'s opening description) doesn't exist yet
  in `Template\Parser` — needed for Part 5, not designed yet.
