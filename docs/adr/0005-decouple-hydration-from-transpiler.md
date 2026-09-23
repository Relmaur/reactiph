# ADR 0005: Prove hydration with a hand-written JS stub before the transpiler exists

- Status: accepted
- Date: 2026-09-22

## Context

Two genuinely hard, independent problems sit back-to-back in the build
order: the hydration protocol (server-rendered HTML + a serialized state
manifest → a client that attaches to existing DOM without re-rendering from
scratch) and the PHP→JS transpiler (ADR 0001/0002/0003). If both are built
together, a bug during end-to-end testing could be in either one, and
there's no way to tell which without unwinding both at once.

## Decision

Part 3 builds and proves the full hydration payload + client bootstrap
against a **hand-written JS stub** standing in for one demo component —
no transpiler involved. Only once that's verified working does Part 4 build
the real transpiler, and Part 5 swaps the stub for real transpiled output.

## Alternatives considered

- **Build hydration and the transpiler together**, wiring real transpiled
  components from the start. Rejected: this is exactly the compounded-risk
  scenario described above — it was explicitly called out as something to
  avoid when this plan was made.

## Consequences

- Part 3's JS stub is throwaway scaffolding, not a deliverable — it should
  be deleted or clearly marked as such once Part 5 replaces it with real
  transpiled component output.
- Part 3 needs its own live verification (an actual browser load, per the
  per-part verification rule in `CLAUDE.md`), since hydration only really
  proves out in a browser, not in PHPUnit.
- This ordering means Part 3 is committing to a hydration *protocol*
  (manifest shape, attach semantics) before the transpiler exists to prove
  it's compatible with transpiled output — Part 5 needs to validate the
  protocol still holds once real transpiled components replace the stub,
  and adjust it if not.
