# ADR 0016: `(click)="method"` via event delegation, and how a component's methods reach the client

- Status: accepted
- Date: 2026-09-22

## Context

Part 5 replaces Part 3's hand-written, single-component `Counter` stub
with real transpiled component output (ADR 0005). That requires two
things the plan names but doesn't design: the `(click)="method"` template
syntax itself (shown in the plan's own example, absent from `Template\Parser`
until now), and how a client runtime actually finds and calls the right
transpiled method — `Transpiler\PhpToJs::transpileMethod()` only ever
handles one method at a time (ADR 0012's "Consequences" flagged this as
an open question). Deliberately scoped as its own slice, separate from
the harder, not-yet-designed question of how a state change reaches the
DOM (see ADR pending on that) — proving click → real transpiled method
execution first, the same incremental discipline as Part 4's slices.

## Decision

**Template syntax.** `Template\Parser` recognizes `(eventName)="methodName"`
as a distinct attribute form (`TagNode::$events`, separate from
`$attributes`). The value must be a bare method name, not a `{$expr}`
expression — enforced at parse time. Only valid on a literal HTML tag;
rejected on a component tag (`<PascalCase />`), since a native DOM event
has no meaning on something that isn't a real element.

**Compiles to a real DOM attribute, not manifest bookkeeping.**
`Template\Compiler` emits `(click)="increment"` as a literal
`data-reactiph-on-click="increment"` HTML attribute on whichever tag
declares it — root or not. This means the hydration manifest
(`Runtime\HydrationSerializer`) needs no changes at all for event
bindings; the binding is fully recoverable by querying the rendered DOM
itself.

**Event delegation from the hydration root, not one listener per bound
element.** `packages/runtime-js/hydrate.js` (the new, generic runtime
replacing Part 3's stub — see "Consequences") attaches exactly one
`click` listener per hydrated component root, using
`event.target.closest('[data-reactiph-on-click]')` (bounded by
`root.contains(target)`, since `closest()` itself searches past `root`)
to find the nearest bound ancestor-or-self and read its method name off
the attribute. No enumeration of bindings at hydration time, no per-element
listener bookkeeping — the DOM attributes already emitted are the only
state needed.

**A component's methods are assembled once per class, into a global JS
registry.** `Transpiler\ComponentTranspiler::transpileComponent()` uses
Reflection to find every method *declared on the component's own class*
(excluding inherited `BaseComponent` methods and magic methods), runs each
through `PhpToJs::transpileMethod()`, and emits one JS snippet registering
them onto `window.ReactiphComponents[componentClass].methods`. This reuses
`Transpiler\MethodSourceReader` (promoted from test-only code in
`ParityTest` — real framework code now, still also used by the parity
suite, so both paths stay provably in sync with what real PHP executes).
Registration is per *class*, not per *instance* — multiple instances of
the same component on one page share one definition. At hydration time,
`hydrate.js` builds each instance as a plain JS object via
`Object.assign({}, payload.state, definition.methods)`, so `this` inside
a called method correctly resolves both state (`this.count`) and
same-object method calls (`this.otherMethod()`) — the exact pattern
`ComponentTranspilerTest`/`ParityTest`'s `quadruple`/`double` case already
proved works at the single-method level.

**Live-verified in an isolated headless browser** (same pattern as Part
3 — see `docs/gotchas.md`): loaded the generated demo, clicked the bound
button twice, and confirmed the *real* transpiled `increment()` mutated
state from 3 → 4 → 5 (matching what real PHP would produce), while the
visible DOM text stayed "3" — proof the method genuinely executed via
the transpiled pipeline, and honest confirmation that DOM-patching is
not yet part of this slice.

## Alternatives considered

- **Per-element listeners, attached during hydration by walking the DOM
  and reading bindings out of the manifest.** Rejected: requires the
  manifest to carry event-binding metadata (new bookkeeping,
  duplicating what's already recoverable from the rendered attributes),
  and means re-walking the subtree on every hydration — delegation from
  one root listener is simpler and is the same technique real frameworks
  use for exactly this reason.
- **Assemble one component instance's methods as bound closures over that
  specific instance** (e.g. `.bind(instance)` at transpile time) instead
  of a shared, class-level method registry read generically at call time.
  Rejected: transpilation is a *class*-level operation (ADR
  0012) — nothing about `PhpToJs::transpileMethod()`'s output is
  instance-specific, so binding it per-instance would just be redundant
  work repeated for every component on the page.

## Consequences

- `packages/runtime-js/hydrate-stub.js` (Part 3's throwaway,
  Counter-specific stub) is deleted — `hydrate.js` is its real,
  general-purpose replacement, exactly as ADR 0005 anticipated.
  `examples/hydrate.php` now demonstrates the real pipeline: SSR render →
  `HydrationSerializer` manifest → `ComponentTranspiler`-assembled method
  JS → `hydrate.js` event delegation → the actual transpiled `increment()`
  running client-side.
- Event bindings only support `click` in practice today — the delegation
  mechanism itself is generic (any `data-reactiph-on-{event}` would work
  the same way), but `hydrate.js` only currently attaches a `click`
  listener. Adding another event type is a small, mechanical follow-up,
  not a design question.
- **The DOM still doesn't reflect the mutated state after a method
  runs.** This is the next, explicitly separate design question (ADR
  0010's hydration-marker work and ADR 0013's array-shape work are the
  closest precedents for how much design weight to expect there) —
  `docs/STATUS.md` tracks it, and it should get its own checkpoint before
  building, the same way arrays did in Part 4.
- `ComponentTranspiler` currently transpiles *every* non-excluded
  method a component declares, whether or not a template actually
  references it (e.g. via an event binding or `{$this->method()}`) —
  no dead-code elimination. Fine at this scale; worth revisiting if
  transpiled-JS payload size becomes a real concern later.
