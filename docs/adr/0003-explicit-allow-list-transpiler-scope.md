# ADR 0003: Transpiler subset and stdlib shim are explicit allow-lists

- Status: accepted (not yet implemented — targets Part 4)
- Date: 2026-09-22

## Context

Once component logic is transpiled to JS (ADR 0001) and executed in the
browser, a transpiler bug or an unsupported PHP construct doesn't fail
loudly — it can silently produce JS that runs but behaves differently from
the PHP original. That's a much worse failure mode than a compile error,
because it surfaces as a confusing runtime UI bug far from its cause.

## Decision

Both the transpiler's supported PHP subset (property get/set, arithmetic/
comparison/boolean ops, `if`/`else`, `for`/`foreach`/`while`, string
concatenation, `$this->method()` calls, array literals/access — see the
build order in `CLAUDE.md` for the authoritative Part 4 scope) and its
stdlib shim (~15-20 builtin functions) are explicit allow-lists. Any PHP
construct or builtin outside the allow-list is a **compile-time error**,
never a best-effort or silently-wrong translation. This mirrors Viewi's own
`RestrictedFunctions.php` guard.

## Alternatives considered

- **Best-effort transpilation** (attempt to translate anything, fall back
  or warn on the unfamiliar). Rejected: "warn and continue" for a
  transpiler means shippable components can silently diverge between
  server and client behavior — exactly the failure mode this project is
  most exposed to, per ADR 0001.

## Consequences

- Component authors get a clear, actionable error at compile time when
  they write PHP the transpiler doesn't support, instead of a mysterious
  client-side bug.
- The allow-list is a hard ceiling on what component logic can do — this
  needs to be documented user-facing (Part 8 docs) so authors know the
  constraint going in, not after hitting it.
- Every addition to the supported subset or stdlib shim is a deliberate,
  reviewed expansion (and should get a parity test — ADR 0006 — proving
  it), not an incidental side effect of some other change.
