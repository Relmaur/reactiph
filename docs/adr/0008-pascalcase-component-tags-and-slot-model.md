# ADR 0008: PascalCase tags resolve via a static ComponentRegistry; slots render in parent scope

- Status: accepted
- Date: 2026-09-22

## Context

Part 2 needed two things the original plan named but didn't fully specify:
how a template distinguishes a custom component tag (`<LikeButton />`) from
a plain HTML element (`<div>`), and how a component receives the markup a
parent places between its open/close tags (`<Card>...</Card>`).

## Decision

**Tag naming convention.** A tag name starting with an uppercase letter is
a component reference; anything else is a literal HTML element
(`TagNode::isComponentName()`). This is the same convention Vue, Angular,
and (per the study of its actual source referenced in the original plan)
Viewi itself use — no explicit marker syntax (e.g. a sigil) is needed.

**Resolution.** Component tags resolve to a class via `ComponentRegistry`,
a static, process-global map from tag name to `class-string<BaseComponent>`
(`ComponentRegistry::register()` / `resolve()`). Compiled templates call
`resolve()` at render time, not compile time — so components can be
registered any time before first render, and a `Card`-in-`Blog` reference
doesn't require `Blog` to know about `Card`'s class at parse time.

**Slot model.** A custom tag's children are compiled and rendered in the
*parent's* variable scope (so `{$expr}` inside slot content sees the
parent's properties/`$this`), producing a plain HTML string that's assigned
to the child instance's `slot` public property before the child renders.
The child's own template accesses it as `{$slot}`. This is a single
unnamed default slot — no named/multiple slots yet.

## Alternatives considered

- **Explicit tag registration syntax in the template itself** (e.g. an
  import-like directive). Rejected: adds a second thing template authors
  must write for every component, for no benefit over a naming convention
  that's already industry-standard.
- **Instance-based (non-static) registry**, threaded through `render()`
  calls explicitly. Rejected for now: the compiled closures have no
  constructor/parameter path to receive one, and Part 2 is single-process
  SSR only — see the trade-off noted directly in `ComponentRegistry`'s
  docblock. Revisit if a later part needs per-request registry isolation.
- **Slot content rendered in the child's scope instead of the parent's.**
  Rejected: it would mean slot content couldn't reference the parent
  component's own state, which defeats the purpose of "let a parent inject
  arbitrary markup into a child" — the parent, not the child, authored that
  markup and should be the one whose variables it resolves against.

## Consequences

- Components must be registered (`ComponentRegistry::register()`) before
  any template referencing them renders, or rendering throws
  `UnknownComponentException` at render time, not compile time — a
  missing registration is a runtime failure mode, not a caught-early one.
  Part 6/8 tooling should consider surfacing this earlier (e.g. a `reactiph
  check` command) once a CLI exists.
- A component named e.g. `Br` or `Img` would collide with the HTML
  void-element list if the PascalCase check weren't applied first — the
  parser and compiler both check `isComponentName()` before consulting the
  void-element list, specifically to avoid that collision.
- Only one, unnamed default slot exists. Named slots (`<Card><span
  slot="footer">...</span></Card>`) are a natural extension if a future
  part needs them, but aren't implemented — don't assume they work.
- `ComponentRegistry`'s static state means tests that register components
  must reset it (`ComponentRegistry::reset()`) to avoid cross-test
  pollution — see the test `#[Before]`/`#[After]` hooks added alongside
  this ADR.
