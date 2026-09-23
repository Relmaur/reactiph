# ADR 0017: DOM patching via SSR comment markers and per-expression thunks

- Status: accepted
- Date: 2026-09-22

## Context

ADR 0016 wired `(click)="method"` to real transpiled method execution, but
deliberately stopped short of updating the DOM afterward — a state
mutation was only observable via `console.log` and a debug attribute. The
plan's own wording ("minimal scoped DOM patch... no full vdom needed")
left three genuinely open questions, surfaced to the user and discussed
before building anything: how the runtime knows *what* to update, whether
that update is triggered by a JS `Proxy` auto-detecting writes or an
explicit step, and how wide "minimal scoped" actually is. All three were
decided together, since the second and third follow from the first.

## Decision

**Marker-based targeted patching, not a template->JS compiler.** A second,
sibling compiler that mirrors `Template\Compiler` but emits JS (handling
component-tag resolution, slots, escaping, etc. all over again) is real,
currently-undesigned scope on the order of Part 4 itself. Instead, the
*existing* SSR compile pass is extended to also emit a small structural
manifest: every text-position `{$expr}` is wrapped in an
`<!--rN--><!--/rN-->` HTML comment marker pair, and the same compile pass
records that expression's raw PHP source at index `N`
(`Template\CompiledTemplate::$expressions`). `Transpiler\ComponentTranspiler`
transpiles each recorded expression into a JS thunk
(`window.ReactiphComponents[class].expressions[N]`) alongside the existing
`methods` map, reusing `PhpToJs`'s expression-level `compileExpr()` (already
private and already expression-shaped — no method/statement machinery
needed) through a new public `transpileExpression()` entry point.

**Markers are emitted only when the rendering component's `hydrationId` is
set**, not unconditionally. `$hydrationId` is already in scope everywhere
in a compiled closure (via `extract(get_object_vars($component))`), so
every marker-emission site is wrapped in the same `isset($hydrationId)`
check `Compiler` already uses for the root's `data-reactiph-id` attribute.
A component that's never hydrated pays zero bytes for a mechanism it'll
never use — confirmed by the full existing test suite passing unmodified
after this change, since no existing test sets `hydrationId` on a template
containing `{$expr}`.

**Explicit patch-after-handler, not a `Proxy`.** The only mutation trigger
today is a bound method running to completion inside the delegated click
listener — a precise, synchronous checkpoint that already existed
structurally in `hydrate.js` (previously used only to serialize a debug
attribute). `Proxy`-based write detection solves a different problem
(uncoordinated, possibly-async mutation sources) that doesn't exist yet,
and would need to recurse into nested arrays/objects to be complete —
reopening ADR 0013's array-duality design for no current benefit. After
`method.call(instance)` returns, `hydrate.js` now recomputes every
expression thunk registered for that component and patches the
corresponding marker pair's contents.

**Scope: text-node content only.** Attribute-value expressions
(`href="{$url}"`) already parse and render server-side but are compiled
through a different code path (`compileHtmlTag`'s attribute loop, not
`compileNode`'s `ExpressionNode` arm) and are deliberately left unmarked —
confirmed by `testAttributeExpressionsAreNeverMarkedEvenWhenHydrationIdIsSet`.
Structural (conditional/list) patching has no real caller yet: the
template syntax has no control-flow directive at all (tracked in
`docs/STATUS.md`'s "Open threads" since Part 2).

**A template `{$expr}`'s bare variables compile differently from a
method's.** This surfaced as a real bug while building this slice, not a
design choice made up front: `{$count}` in a template is sugar for the
SSR-extracted component property (`extract(get_object_vars($component))`),
so `PhpToJs::compileExpr()` — built for method bodies, where a bare
variable is a genuine local — emitted a bare `count` identifier with
nothing to bind it client-side. `transpileExpression()` sets a private
`$inTemplateExpression` flag for the duration of the call, and
`compileVariable()` rewrites every non-`$this` variable to a `this.`
property access only in that mode. Safe specifically because the
transpiler's allow-listed expression subset (ADR 0011) has no closures or
arrow functions, so there is no way for a *real* local variable to appear
inside a template expression under that subset — every bare variable
reaching `compileVariable()` in template mode is provably a property, not
guessed to be one.

**Live-verified in an isolated headless browser**: loaded the regenerated
`examples/hydrate.php` demo, clicked the bound button twice, and confirmed
the visible `<span class="count">` text patched 3 → 4 → 5 in step with the
real transpiled `increment()` mutating state — matching real PHP — while
the marker comment pair itself survived both patches intact (a third click
would still find the right spot).

## Alternatives considered

- **A JS template->JS compiler mirroring `Template\Compiler`.** Rejected
  for now: real, currently-undesigned scope (component-tag resolution,
  slot handling, and escaping would all need a second implementation),
  more naturally revisited once markup control-flow exists and needs
  structural patching anyway — building it earlier means guessing its
  shape without that requirement in hand.
- **`Proxy`-wrapped component state for automatic write detection.**
  Rejected: no current mutation source needs automatic detection (the
  delegated click listener already knows exactly when to check), and deep
  reactivity would require recursing into PHP's array-duality JS
  representations (ADR 0013), which is real added complexity for no
  present benefit.
- **Marker indices unique page-wide instead of per-class.** Considered
  briefly to sidestep the nested-hydration-root collision case below, but
  rejected as unnecessary: bounding the DOM walk at nested
  `data-reactiph-id` elements (see Consequences) solves the same problem
  more cheaply, without threading a global counter through what is
  otherwise a purely per-class, cacheable compile pass.

## Consequences

- **`packages/runtime-js/hydrate.js` now requires `php-runtime.js` to be
  loaded first** — expression thunks call `__phpString()` to match SSR's
  `(string)` cast (the same shim ADR 0014 introduced for concatenation,
  now load-bearing for every patched value, not just an incidental
  dependency of whichever methods happen to use it).
  `examples/hydrate.php` was updated to load it; this was a real gap
  before (the demo happened to work without it only because `increment()`
  itself doesn't call any runtime helper).
- **Marker indices are unique per component *class*, not page-wide.**
  `hydrate.js`'s marker-walking `TreeWalker` explicitly rejects descending
  into any nested element carrying its own `data-reactiph-id` (a
  separately-hydrated component with its own numbering), so a parent and
  a nested child both having an index-0 expression can't cross-contaminate
  — not yet exercised by a real nested-hydration example, but the
  narrower design (a page-wide counter threaded through compilation) was
  judged unnecessary complexity for a case the boundary-check already
  handles cheaply.
- `Template\Compiler::compile()` now returns `Template\CompiledTemplate`
  (a `render` closure plus an `expressions` array) instead of a bare
  `Closure` — a public API change. `BaseComponent::$rendererCache` became
  `$compiledTemplateCache`, and a new public static
  `BaseComponent::compiledTemplateFor(class-string): CompiledTemplate`
  exists specifically so `ComponentTranspiler` can read a class's
  expression list without an existing instance — this imposes a new
  implicit constraint that every component class must be constructible
  with no arguments, since a cache-miss compile builds a throwaway
  instance solely to call `template()` on it.
- Attribute-value and structural DOM patching remain explicitly
  unbuilt — the next extension of this mechanism if templates grow a
  control-flow directive or dynamic attributes need patching, not a
  concern for the current template subset.
