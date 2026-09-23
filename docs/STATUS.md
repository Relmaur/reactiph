# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 2 — Component tree: custom tags, props, nesting, slots: done, verified.**

- `<PascalCase />` tags resolve to a child component class via the static
  `Component\ComponentRegistry` (`register()`/`resolve()`/`reset()`).
  Attribute values become props assigned onto the child instance; the
  tag's children render in the *parent's* scope into the child's `slot`
  property. See ADR 0008.
- `Template\Node\TagNode` gained a `selfClosing` flag (distinguishing
  `<img>`/`<hr />` from an explicitly-empty `<div></div>`) — needed to fix
  a real bug where void elements got a spurious closing tag emitted; see
  `docs/gotchas.md`.
- End-to-end verification: `ComponentTreeTest` renders a Blog → Thumbnail →
  LikeButton nested tree to static HTML, per the build plan's Part 2
  verification requirement. Runnable smoke test: `examples/blog.php`.
- **Also built (prompted mid-Part-2, scoped as standing infrastructure
  rather than its own Part): a common exception foundation.** Every
  framework exception now implements `Exception\ReactiphException`
  (`code()`, `hint()`, `context()`, JSON-serializable), built only via
  named static constructors. Retrofitted onto the three exceptions that
  existed (`ParseException` — 11 named constructors, `UnknownComponentException`,
  new `InvalidComponentException`) plus a new `CompilerException` for the
  compiler's one internal-invariant case. See ADR 0009 and
  `examples/errors.php`. This is a standing convention for every future
  part, not a checklist item that's "done."
- 35 PHPUnit tests passing. PHPStan (level 8) and PHP-CS-Fixer both clean;
  `composer check` runs all three.
- Committed and pushed to `origin/main` — https://github.com/Relmaur/ractiph.

## Next up

**Part 3 — Hydration payload + client bootstrap (transpiler-free).** Not
started. Needs: `Runtime` serializes the rendered component tree (class,
props, state, DOM position) as a JSON manifest embedded in the page;
`packages/runtime-js` ships a hand-written JS stub for one demo component
proving server HTML + manifest → client attaches without re-rendering from
scratch. Per ADR 0005, this is deliberately transpiler-free — Part 4 (the
transpiler) comes after. Verification requires an actual browser load, not
just PHPUnit (see "Verification per part" in `CLAUDE.md`).

## Remaining parts (unstarted)

4. PHP→JS transpiler MVP — highest risk, timeboxed spike.
5. Reactive client runtime.
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
