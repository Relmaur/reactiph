# ADR 0014: Fix boolean stringification in transpiled string concatenation

- Status: accepted
- Date: 2026-09-22

## Context

While starting work on stdlib builtins (specifically `implode()`, which
needed a PHP-accurate stringification helper), re-examined how slice 1's
`compileConcat()` stringifies its operands and found a real, already-shipped
bug: it used JS's native `String()`, but **PHP and JS disagree on how a
boolean stringifies**. Verified empirically before writing any fix (`php
-r 'var_dump("x" . true, "x" . false);'` gives `"x1"` and `"x"`; the
equivalent JS `"x" + String(true)` / `"x" + String(false)` gives `"xtrue"`
and `"xfalse"`). Any transpiled component method concatenating a boolean
property — a very ordinary thing to do (`'Status: ' . $this->isActive`) —
would have produced visibly wrong output in the browser while looking
completely correct in code review. This is exactly the failure mode ADR
0003's allow-list philosophy exists to prevent, and it slipped through
slice 1 (ADR 0011) because that slice's parity cases for concatenation
only exercised string operands, never a boolean one.

## Decision

Added `__phpString()` to `packages/runtime-js/php-runtime.js`, replicating
PHP's actual string-cast rules: `true` → `"1"`, `false`/`null`/`undefined`
→ `""`, everything else → JS's native `String()` (which already matches
PHP for the types this slice handles — numbers and strings).
`Compiler\PhpToJs::compileConcat()` now wraps both operands in
`__phpString(...)` instead of `String(...)`. Two new parity cases
(`activeLabel`, using a real `bool $active` fixture property) specifically
exercise both `true` and `false` concatenation — these fail against the
old raw-`String()` codegen and pass against the fix, so they're a real
regression guard, not just a shape assertion.

## Alternatives considered

None seriously — this is a straightforward bug fix once identified, not a
design fork. The only real decision was *whether* to treat it as
in-scope to fix now versus deferring: fixing immediately was the obvious
call, since it's a correctness bug in already-committed, already-pushed
code, not speculative future scope.

## Consequences

- Any future stdlib builtin that stringifies a value (starting with the
  planned `implode()`) should use `__phpString()` for the same reason,
  not JS's native `String()` — this fix establishes that as the pattern.
- This is a reminder that a slice's parity cases need to cover the full
  *type* space a construct can see in practice, not just the types the
  slice's own examples happened to use — string concatenation was "done"
  in ADR 0011 with tests that only ever passed it strings. Worth
  revisiting other already-shipped constructs (arithmetic, comparisons)
  with the same question — are there types in the fixture's own property
  set that were never actually tried against them? — rather than assuming
  passing tests mean full type coverage.
