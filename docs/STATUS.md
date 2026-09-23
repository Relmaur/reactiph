# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 5 — Reactive client runtime: complete and verified.** Both
click-to-real-transpiled-method-execution (slice 1) and DOM patching after
a state change (slice 2) are built, tested, and live-verified in a
browser.

Before building slice 2, three real open design questions were surfaced to
the user and discussed rather than picked unilaterally: how the runtime
knows what to update, whether "reactive" means a `Proxy` or an explicit
trigger, and how wide "minimal scoped patch" is. The user chose to discuss
tradeoffs rather than take the first recommendation outright; all three
were settled together (see ADR 0017) since the second and third follow
from the first.

- **DOM patching via SSR comment markers**, not a second template→JS
  compiler and not a `Proxy`. `Template\Compiler` wraps every
  text-position `{$expr}` in an `<!--rN--><!--/rN-->` marker pair —
  emitted only when the rendering component's `hydrationId` is set, so a
  plain SSR-only render is byte-identical to before this part (confirmed:
  all 129 pre-existing tests passed unmodified). `Template\Compiler::compile()`
  now returns `Template\CompiledTemplate` (a `render` closure plus an
  ordered `expressions` array of raw PHP source, one per marker index)
  instead of a bare `Closure`.
- **`BaseComponent::compiledTemplateFor(class-string): CompiledTemplate`**
  (new, public, static) — the compile-and-cache path `render()` already
  had, now also reachable without an existing instance, so
  `ComponentTranspiler` can read a class's expression list. This imposes
  a new implicit constraint: every component class must be constructible
  with no arguments (a cache-miss compile builds a throwaway instance
  just to call `template()`).
- **`PhpToJs::transpileExpression()`** (new, public) — transpiles one
  standalone PHP expression via the existing (already expression-shaped,
  already private) `compileExpr()`, independent of `transpileMethod()`'s
  statement/local-variable machinery.
- **Real bug caught and fixed**: a template `{$count}` and a method-body
  `$count` mean different things to the transpiler — the former is sugar
  for an SSR-extracted property, the latter a real local. Reusing
  `compileExpr()` naively compiled `{$count}` to a bare, unbound `count`
  identifier. Fixed with an `$inTemplateExpression` mode flag; documented
  in `docs/gotchas.md` (Part 5 section) and ADR 0017.
- **`ComponentTranspiler`** now assembles an `expressions` map alongside
  `methods`, each entry a JS thunk wrapped in `__phpString()` to match
  SSR's `(string)` cast exactly (same shim ADR 0014 introduced).
- **`hydrate.js`** recomputes and patches every expression marker for a
  component right after its bound method returns — the same synchronous
  checkpoint the delegated click listener already had. The marker-finding
  `TreeWalker` explicitly refuses to descend into a nested element with
  its own `data-reactiph-id`, since marker indices are only unique per
  component *class*, not page-wide.
- `examples/hydrate.php` now also loads `packages/runtime-js/php-runtime.js`
  (a real, previously-latent gap — the demo only worked without it because
  `increment()` doesn't happen to call any runtime helper; expression
  thunks now genuinely need `__phpString()`).
- **Live-verified in an isolated headless browser**: clicked the bound
  button twice on the regenerated demo; the visible `<span class="count">`
  text patched 3 → 4 → 5 in step with the real transpiled `increment()`
  mutating state, matching real PHP — and the marker comment pair survived
  both patches intact.
- 140 PHPUnit tests passing (11 new this slice). PHPStan (level 8) and
  PHP-CS-Fixer both clean.
- Not yet committed as of this status update.

## Next up

**User go-ahead needed before starting Part 6** (Bridge abstraction +
`DefaultBridge`), per the working agreement — Part 5 is done and reported,
not a mid-part checkpoint this time.

If/when Part 5 continues instead: attribute-value patching and structural
(conditional/list) patching are the two explicitly-deferred extensions of
this same mechanism (see ADR 0017's Consequences) — attribute patching is
a plausible near-term follow-up; structural patching has no real caller
until markup control-flow syntax exists at all (see "Open threads" below,
unchanged since Part 2).

## Remaining parts (unstarted)

6. Bridge abstraction + `DefaultBridge`.
7. `reactiph/wordpress-bridge` package.
8. CLI/dev tooling + docs.

## Open threads / not yet decided

- Template syntax has no control-flow directive (`@foreach`/`@if` or
  similar) for dynamically rendering a variable-length list of children —
  the plan's Part 2 scope didn't call for it, so the Part 2 Blog example
  hardcodes a fixed number of `<Thumbnail />` tags rather than looping
  over an array. Not designed yet. This also gates structural DOM patching
  (ADR 0017) — there's nothing to patch structurally until this exists.
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
- DOM patching is scoped to text-node content only — attribute-value and
  structural patching are both explicitly deferred (ADR 0017).
- Nested-component hydration (a component with its own `hydrationId`
  rendered inside another hydrated component's subtree) has a defensive
  boundary check in `hydrate.js`'s marker walker but no real example or
  test exercises it yet — worth a dedicated check once Part 6/7 produces
  a multi-component page.
