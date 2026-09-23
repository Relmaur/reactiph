# ADR 0006: Parity-test the transpiler from day one of Part 4

- Status: accepted (not yet implemented — targets Part 4)
- Date: 2026-09-22

## Context

There's no static way to be confident a hand-written PHP→JS transpiler
(ADR 0001/0002) produces *behaviorally equivalent* output — the only real
signal is running the same logic both ways and comparing results. Viewi
itself solves this with a parity suite (`JsTranspilerTest`,
`tests/Support/Data/parity/`).

## Decision

For every supported construct in the transpiler's allow-list (ADR 0003),
run the same PHP method through real PHP and through transpiled-JS-in-Node,
and assert identical output. Build this parity suite starting on day one of
Part 4 — alongside the first supported construct, not bolted on after the
transpiler "seems to work."

## Alternatives considered

- **Trust hand-inspection of generated JS.** Rejected: doesn't scale past
  the first few constructs, and gives false confidence — subtle semantic
  differences between PHP and JS (type coercion, array vs. object
  semantics, string/number comparison) are exactly the class of bug that
  looks fine on read-through and breaks at runtime.
- **Build the transpiler first, add parity tests once the subset is
  "done."** Rejected: this is how transpiler bugs compound silently across
  many constructs before anyone notices — the whole point of parity
  testing is to catch divergence at the moment a construct is added.

## Consequences

- Part 4 requires a working Node execution environment as a test
  dependency (transpiled JS has to actually run somewhere to compare
  output) — this is new tooling surface area beyond PHPUnit alone.
- Per the per-part verification rule (`CLAUDE.md`), Part 4 isn't "done" on
  PHPUnit alone once this suite exists — a live Node-executed parity
  assertion is required.
- Every future addition to the allow-listed subset or stdlib shim (ADR
  0003) should ship with a corresponding parity test case, not just a unit
  test of the transpiler's AST output.
