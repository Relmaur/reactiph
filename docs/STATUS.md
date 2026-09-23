# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 3 — Hydration payload + client bootstrap (transpiler-free): done, verified.**

- `BaseComponent::$hydrationId` (nullable, default null): when set,
  `Compiler` emits `data-reactiph-id="..."` on the template's root element
  — only when that root is a single literal HTML tag (see "Open threads"
  below for the current limitation). Fully additive to Part 1/2 behavior;
  all 35 pre-existing tests kept passing unmodified throughout.
- `Runtime\HydrationPayload` / `Runtime\HydrationSerializer`: build a
  payload (id, component class, public state minus `slot`/`hydrationId`)
  from a rendered component and serialize a list of them into a
  `#reactiph-hydration` JSON `<script>` tag, safely escaped against
  `</script>`-breakout via `JSON_HEX_*` flags. See ADR 0010.
- `packages/runtime-js/hydrate-stub.js`: a hand-written, Counter-specific
  hydration script — deliberately throwaway scaffolding per ADR 0005, to
  be replaced entirely by Part 5's generic reactive runtime.
- **Live-verified in a real (headless, isolated) browser**, per the
  per-part verification rule: `examples/hydrate.php` generates a static
  page; loaded it, confirmed SSR shows count 3 with the root correctly
  marked `data-reactiph-id="counter-1"`, clicked Increment twice, and
  confirmed the DOM updated to 5 while the embedded manifest's raw JSON
  still read 3 — proving direct DOM mutation, not a re-render — with zero
  console errors. Used an isolated `puppeteer-core` script rather than the
  `chrome-devtools-mcp` tool directly, because another active session had
  the shared browser profile locked; see `docs/gotchas.md`.
- 44 PHPUnit tests passing. PHPStan (level 8) and PHP-CS-Fixer both clean.
- Committed and pushed to `origin/main` — https://github.com/Relmaur/reactiph
  (renamed from `ractiph` by the user after Part 2; remote URL updated
  accordingly, same commit history).

## Next up

**Part 4 — PHP→JS transpiler MVP.** Not started. **Highest risk — treat
as a timeboxed spike, confirm scope is holding before continuing to Part
5.** Needs: `Transpiler\PhpToJs` (nikic/php-parser-based, add it as a
direct dependency — see `docs/gotchas.md`'s Part 1 entry on it currently
only being a transitive PHPUnit dependency) supporting a documented PHP
subset (property get/set, arithmetic/comparison/boolean ops, if/else,
for/foreach/while, string concat, `$this->method()` calls, arrays) per
ADR 0003's allow-list philosophy, plus `Transpiler\Stdlib` shimming
~15-20 builtins. Build the PHP-vs-transpiled-JS parity suite (Node
required) from day one of this part, per ADR 0006 — not after. Every new
error case this part introduces should follow ADR 0009 (implement
`ReactiphException`, named constructors) from the start.

## Remaining parts (unstarted)

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
- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently a silent no-op (ADR 0010) — no id gets
  emitted anywhere, and nothing on the PHP side catches the resulting
  payload/DOM mismatch. Fine for Part 3's one demo component; needs a real
  decision (most likely: enforce single-root globally, per ADR 0010's
  "Alternatives") once Part 5 hydrates more than one component.
- No allow-list yet for which public properties should actually reach the
  client as hydration state vs. stay server-only (ADR 0010) — everything
  public except `slot`/`hydrationId` is serialized today.
- `packages/runtime-js` has no `package.json`/npm tooling yet — deferred
  deliberately until Part 5 needs real JS build tooling; Part 3's stub is
  a single plain `.js` file with no dependencies.
