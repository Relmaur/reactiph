# ADR 0010: Hydration id marking, manifest shape, and script-tag embedding

- Status: accepted
- Date: 2026-09-22

## Context

Part 3 needed to prove the hydration protocol ADR 0005 called for: SSR
HTML + an embedded manifest → a client script attaches to the existing DOM
without re-rendering. That requires deciding three things the original
plan didn't specify at this level of detail: how a client script finds
*which* DOM node belongs to *which* server-rendered component, what a
"manifest" concretely is and where it lives on the page, and how to embed
component state as JSON in HTML without creating an injection vector.

## Decision

**Root marking.** A component's compiled render closure conditionally
emits `data-reactiph-id="..."` on its template's root element when
`BaseComponent::$hydrationId` is set (`Compiler::compileHtmlTag()`,
`isRoot` case). This only applies when the template's single top-level
node is a literal HTML tag — enforced structurally (the marking code path
is only reachable for that shape), not by rejecting other shapes. A
component whose template has multiple top-level nodes or a component tag
as its root can still have `hydrationId` set, but the id is never emitted
anywhere — a documented no-op, not an error (see Consequences).

**Manifest.** `Runtime\HydrationSerializer::payloadFor()` builds a
`HydrationPayload` (id, component class, public state minus `slot` and
`hydrationId` themselves) from a rendered component.
`HydrationSerializer::toScriptTag()` serializes a list of these as a JSON
array inside `<script type="application/json" id="reactiph-hydration">`,
embedded directly in the page — the same "JSON island" pattern used by
Next.js's `__NEXT_DATA__`, Nuxt's `__NUXT__`, etc.

**Safe embedding.** The JSON is encoded with
`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. Without
this, component state containing the literal string `</script>` would
prematurely close the manifest's script tag — a real injection vector
(anything that reaches the page as HTML afterward executes), not a
theoretical one, since component state is exactly the kind of value that
often originates from user input. Covered by
`HydrationSerializerTest::testToScriptTagEscapesStateThatWouldBreakOutOfTheScriptTag()`.

## Alternatives considered

- **Enforce a single-HTML-root constraint on every template, globally,
  now.** Rejected: this would have been a breaking change to Part 1/2
  behavior already shipped and tested (`LikeButton`/`Card` render fine as
  standalone multi-root-capable or component-rooted templates today, and
  several existing tests exercise exactly that). Hydration is currently
  needed for one demo component, not universally — imposing a global
  constraint to serve a narrow, not-yet-general need is exactly the kind
  of premature design ADR 0001-0009's restraint has been trying to avoid.
  Revisit this when Part 5 needs to hydrate a *whole tree*, at which point
  "every hydration root needs a single element" becomes an actual
  requirement rather than a speculative one.
- **Inject the id via string manipulation of the rendered HTML** (regex or
  DOM-parse the output and splice in the attribute) instead of compiling
  it in. Rejected: fragile (HTML-in-a-string manipulation is exactly the
  kind of thing this project's own template compiler exists to avoid) and
  slower (a second parse pass per render), when the compiler already knows
  the root tag's shape at compile time for free.
- **A data attribute per node instead of one root + a script-tag
  manifest** (e.g., inline `data-reactiph-state='{"count":3}'` on the root
  itself). Rejected: doesn't scale to multiple hydration roots on one page
  without repeating the same escaping problem at every node, and mixes
  structural markup with a payload that's more naturally one JSON blob.

## Consequences

- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently silent — no error, no id emitted, and if that
  component is then also handed to `HydrationSerializer::payloadFor()`,
  the resulting payload's `id` won't correspond to any real DOM node. The
  client stub logs a console error in that case (`no DOM node for
  hydration id`), but nothing on the PHP side catches the mismatch at
  compile or render time. This is a known rough edge, not a design gap
  Part 3 needed to close — flagged in `docs/STATUS.md`, to be resolved
  (likely via the single-root constraint the "Alternatives" section
  deferred) when Part 5 needs to hydrate more than one demo component.
- `data-reactiph-id` and the `#reactiph-hydration` script tag are now
  part of the framework's client-facing contract — any future client
  runtime (Part 5's real one included) needs to keep reading manifests in
  this shape, or this ADR needs superseding.
- Component state serialized into the manifest is whatever
  `get_object_vars()` returns minus `slot`/`hydrationId` — i.e. every
  other public property, unfiltered. There's no allow-list yet for what
  state actually needs to reach the client vs. server-only public state a
  component might have for its own SSR logic. Not a problem for Part 3's
  one-property `Counter` demo; worth revisiting once real components have
  richer state.
