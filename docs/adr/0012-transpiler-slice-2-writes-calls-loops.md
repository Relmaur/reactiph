# ADR 0012: Transpiler slice 2 — property writes, method calls, loops

- Status: accepted
- Date: 2026-09-22

## Context

Slice 1 (ADR 0011) covered reads, arithmetic/comparison/boolean
operators, and `if`/`elseif`/`else`. The user approved continuing to
expand the transpiler's subset toward the plan's full documented set
without another explicit checkpoint each time, so this slice adds
property *writes*, `$this->method()` calls, increment/decrement, and
`while`/`for` loops — everything from the original plan except arrays,
`foreach`, and the stdlib builtins, which are deliberately left for a
further slice given their genuinely different order of complexity (PHP's
array type has no single JS equivalent — see `docs/STATUS.md`).

## Decision

**Property writes** (`$this->prop = expr;`) are now supported —
`Expr\Assign`'s target can be a `Variable` or a `PropertyFetch`, compiling
to `this.prop = ...` the same way a read compiles to `this.prop`. No
special handling needed beyond removing the exclusion; a property write
has no PHP/JS semantic gap the way truthiness or loose equality do.

**Method calls** (`$this->method(...)`) compile to a plain JS method call
on the same target expression, with **positional arguments only** — named
arguments and argument unpacking (`...$args`) throw
`TranspileException::unsupportedConstruct()`. This assumes whatever calls
the transpiled method also has the called method transpiled and attached
to the same object; `PhpToJs` itself has no opinion on that — it's a
concern for whatever assembles a full component's methods (Part 5).

**`while` and `for` loops** compile directly, with the same `__phpBool()`
truthiness wrapping the `if` statement already uses applied to their
condition(s) too. **`for` loop clauses are restricted to at most one
expression each** (`stmt->init`/`cond`/`loop` each ≤ 1) — PHP allows
comma-separated multiple expressions in each clause (e.g.
`for ($i = 0, $j = 0; ...)`), which is rare in practice and would need
either a JS comma-operator translation or restructuring; rejected as
added complexity for a pattern none of the realistic component-method
targets need.

**Increment/decrement** (`++`/`--`, pre and post) map directly — PHP and
JS agree on these for numeric operands, no shim needed.

## Alternatives considered

- **Support arrays and `foreach` in this same slice**, since the plan
  lists them alongside loops as one subset. Rejected: PHP arrays are
  simultaneously ordered lists and string-keyed maps, with no single JS
  type covering both faithfully — that decision (JS `Array` for list-style,
  plain `Object` for associative, and how `foreach` needs to dispatch
  between them at runtime) deserves its own focused pass and parity tests,
  not to be bundled in alongside loops as if it were equally
  straightforward.
- **Support comma-separated multi-expression `for` clauses.** Rejected —
  see Decision above; flagged as a known gap rather than engineered for
  speculatively.

## Consequences

- A component method that calls another method on `$this` can now be
  transpiled — but only that one method in isolation; nothing in
  `PhpToJs` verifies the called method is *also* transpilable or attaches
  it automatically. `ParityTest`'s `quadruple`/`double` case demonstrates
  the pattern a caller (eventually Part 5) needs to follow: transpile both
  methods, attach both onto the same JS object.
- Arrays, `foreach`, and the stdlib builtins remain the only pieces of the
  plan's original Part 4 subset left unimplemented. `docs/STATUS.md`
  tracks this as the next slice.
- A parity test that calls a *mutating* method (`incrementA`, via property
  write) surfaced a real test-harness ordering bug — see the "Part 4"
  entry in `docs/gotchas.md`. Worth remembering for any future parity
  case involving a property write: capture the JS-side initial state
  *before* invoking the real PHP method, not after.
