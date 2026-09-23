# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 5 — Reactive client runtime: slice 1 done and verified (click
bindings + real transpiled method execution). Paused before the DOM-patch
design, per the same checkpoint discipline Part 4 used for arrays.**

Told the user upfront this part has two separable concerns — wiring
`(click)="method"` to real transpiled execution, and how a resulting
state change reaches the DOM — and that the second is where "no full
vdom needed" leaves genuine design space worth a checkpoint. Built the
first as its own slice without asking further; this entry is that report.

- **Template syntax**: `Template\Parser` now recognizes `(eventName)="methodName"`
  as a distinct attribute form (`TagNode::$events`) — value must be a bare
  method name, not `{$expr}`; rejected on component tags (only makes
  sense on a real DOM element).
- **Compiles to a real DOM attribute**: `Template\Compiler` emits
  `data-reactiph-on-click="increment"` directly — no hydration-manifest
  changes needed, the binding is fully recoverable from the rendered DOM.
- **`Transpiler\ComponentTranspiler`** (new): assembles a component
  class's own declared methods (Reflection-based, excluding inherited/magic
  ones) into one JS definition per *class* (not per instance), registered
  on `window.ReactiphComponents`. Reuses `Transpiler\MethodSourceReader`
  (promoted from test-only code — real framework code now, still also used
  by the parity suite).
- **`packages/runtime-js/hydrate.js`** (new, generic — replaces Part 3's
  throwaway `hydrate-stub.js`, deleted): one delegated `click` listener
  per hydration root, matching descendants via
  `closest('[data-reactiph-on-click]')` bounded by `root.contains()`.
  Builds each instance as `Object.assign({}, state, methods)` so
  `this.otherMethod()` calls inside a transpiled method resolve correctly.
- **Live-verified in an isolated headless browser**: clicked a bound
  button twice on the regenerated `examples/hydrate.php` demo; confirmed
  the *real* transpiled `increment()` mutated state 3 → 4 → 5 across the
  two clicks (matching real PHP), while the visible DOM text correctly
  stayed "3" — proof the method genuinely ran via the transpiled
  pipeline, and honest confirmation the next piece (DOM patching) isn't
  built yet.
- 129 PHPUnit tests passing (Transpiler suite: 76). PHPStan (level 8) and
  PHP-CS-Fixer both clean.
- Not yet committed as of this status update.

## Next up

**Part 5 continued — reactive DOM update after a state change.** This is
the piece flagged as needing its own checkpoint before design: currently,
running a bound method mutates the client-side component instance but the
DOM stays exactly as server-rendered. Real open questions, not yet
decided:
- How does the runtime *know* what to update — re-run a JS-transpiled
  version of the whole template (a template→JS compiler mirroring
  `Template\Compiler`, which doesn't exist yet), or mark individual
  `{$expr}` interpolation points during SSR and only re-evaluate/patch
  those specific spots?
- Does "reactive" mean wrapping component state in a JS `Proxy` (per the
  plan's own wording) to detect writes automatically, or is an explicit
  "mark dirty and patch after the event handler returns" step (what
  slice 1's `hydrate.js` already does structurally, minus the actual
  patch) sufficient and simpler?
- Scope of "minimal scoped DOM patch" (plan's phrase) — text-node-level
  patching only, or also attributes, or also structural (conditional/
  list) content? The current template subset has no conditionals or
  loops in markup (see "Open threads"), which narrows what patching
  actually needs to handle for now.

## Remaining parts (unstarted)

6. Bridge abstraction + `DefaultBridge`.
7. `reactiph/wordpress-bridge` package.
8. CLI/dev tooling + docs.

## Open threads / not yet decided

- Template syntax has no control-flow directive (`@foreach`/`@if` or
  similar) for dynamically rendering a variable-length list of children —
  the plan's Part 2 scope didn't call for it, so the Part 2 Blog example
  hardcodes a fixed number of `<Thumbnail />` tags rather than looping
  over an array. Not designed yet.
- Only one, unnamed default slot exists per component (ADR 0008) — no
  named/multiple slots.
- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently a silent no-op (ADR 0010) — needs a real
  decision (most likely: enforce single-root globally) once more than one
  component needs hydrating on a page at once.
- No allow-list yet for which public properties should actually reach the
  client as hydration state vs. stay server-only (ADR 0010).
- `packages/runtime-js` has no `package.json`/npm tooling yet — deferred
  deliberately until real JS build tooling is needed.
- Closures are entirely unimplemented in the transpiler — blocks
  `array_map`/`array_filter` (ADR 0015).
- Event bindings only support `click` in practice — the delegation
  mechanism (ADR 0016) is generic, but `hydrate.js` only attaches a click
  listener today. Small, mechanical follow-up, not a design question.
- `ComponentTranspiler` transpiles every non-excluded method a component
  declares regardless of whether a template actually references it — no
  dead-code elimination (ADR 0016). Fine at current scale.
- The DOM-update/reactivity mechanism itself — the main "Next up" item
  above.
