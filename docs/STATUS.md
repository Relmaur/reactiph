# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current work

**Folder-based components (ADR 0020) — built, tested, verified. Not one
of the original 8 build-order parts** — an additive "sugar layer" the
user requested after Part 7, on top of the existing component model, not
a replacement for it.

Two real forks were discussed with the user before building: how a
template stops needing to live inline (chose: bake a default sibling-file
lookup directly into `BaseComponent`, not an opt-in trait, not "no
framework change"), and when discovery/registration happens (chose:
request-time directory scan, not deferred to Part 8, not
autoload/marker-interface based).

- **`BaseComponent::template()` is no longer abstract.** A component that
  doesn't override it loads its markup from a sibling
  `{ShortClassName}.reactiph.html` file, located via reflection on the
  component's own class file. Every existing component still overrides
  `template()` inline and is completely unaffected — confirmed by the
  full pre-existing test suite passing unmodified.
- **`Component\ComponentDiscovery::registerDirectory()`** (new) —
  auto-registers every component found under a directory by its short
  class name. Statically parses each `.php` file with `nikic/php-parser`
  to learn its declared class *without including or evaluating the
  file*; only loads a class (via the normal Composer autoloader) once its
  name is known this way. Skips anything that doesn't resolve through
  normal PSR-4 autoloading, and skips abstract `BaseComponent` subclasses.
- **`Component\MissingTemplateFileException`** (new) — thrown when a
  component neither overrides `template()` nor has a matching sibling
  file, naming the exact path it looked for.
- **Styles are explicitly out of scope** — no asset pipeline exists
  anywhere in `packages/runtime-js` yet, so a folder-based component's
  stylesheet is, for now, just an inert file.
- **Terminology**: called "components" throughout, deliberately never
  "blocks" — avoids colliding with the actual planned WordPress Gutenberg
  *block* integration (ADR 0004/0019).
- **`examples/folder-components.php`** (new) — a real, runnable
  end-to-end demo: `Greeting` (`examples/folder-based/`) never overrides
  `template()` and is never manually registered, yet resolves correctly
  as `<Greeting />` from a parent template, purely through discovery +
  the default template lookup. Required a new PSR-4 autoload-dev mapping
  (`ReactiphExamples\ => examples/folder-based/`) so discovery has a real
  autoloadable class to find.
- 166 PHPUnit tests passing (6 new). PHPStan (level 8) and PHP-CS-Fixer
  both clean.
- Not yet committed as of this status update.

## Next up

No user go-ahead has been given yet for what comes after this. Two threads
are live and unstarted:

1. **Live verification against a real WordPress site** — still the
   immediate next step for Part 7 specifically, by the user's own earlier
   choice. Needs Local by Flywheel's app started (the TAW site,
   `~/Local Sites/taw`, wasn't running last checked — returned 502), then
   `examples/wordpress-plugin/` symlinked into that site's
   `wp-content/plugins/`, activated, and exercised for real.
2. **Part 8 — CLI/dev tooling + docs.** The "+ docs" half is now covered
   by the separate `reactiph-docs` documentation site
   (`~/Documents/reactiph-docs`, 18 pages, Documentation.AI-structured).
   The CLI itself (`bin/reactiph`, watch mode, production build) doesn't
   exist yet. A cached component-discovery manifest (see "Open threads")
   is natural scope for this CLI once it exists.

## Remaining parts (unstarted)

8. CLI/dev tooling + docs (docs half now substantially covered by the
   separate `reactiph-docs` site).

## Open threads / not yet decided

- Template syntax has no control-flow directive (`@foreach`/`@if` or
  similar). Also gates structural DOM patching (ADR 0017) and a Gutenberg
  block (ADR 0019).
- Only one, unnamed default slot exists per component (ADR 0008).
- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently a silent no-op (ADR 0010).
- No allow-list yet for which public properties should actually reach the
  client as hydration state vs. stay server-only (ADR 0010).
- `packages/runtime-js` has no `package.json`/npm tooling yet — blocks a
  Gutenberg block (ADR 0019) and any real asset pipeline for folder-based
  component styles (ADR 0020).
- Closures are entirely unimplemented in the transpiler (ADR 0015).
- Event bindings only support `click` in practice (ADR 0016).
- DOM patching is scoped to text-node content only (ADR 0017).
- Nested-component hydration has a defensive boundary check in
  `hydrate.js`'s marker walker but no real example/test exercises it yet.
- **No mechanism marks a component method as server-only vs.
  client-transpiled** (ADR 0018) — no demo component built so far has
  forced the question for real.
- **`RpcHandler` (core) has no authentication/authorization of its own**
  (ADR 0018) — resolved for WordPress specifically (a `wp_rest` nonce),
  not for `DefaultBridge`/a plain PHP app.
- **Live WordPress verification hasn't happened yet** (ADR 0019) — see
  "Next up".
- A Gutenberg block (ADR 0004/0019) is still unbuilt.
- **`ComponentDiscovery::registerDirectory()` is an uncached, per-request
  filesystem scan + re-parse** (ADR 0020) — deliberate for now; a cached
  manifest is natural Part 8 CLI scope.
- **Folder-based component styles have nowhere to be served from** (ADR
  0020) — same underlying gap as the Gutenberg block's blocker.
