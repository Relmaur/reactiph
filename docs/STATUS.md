# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 7 — `reactiph/wordpress-bridge` package: built and unit-tested,
live verification deliberately deferred.**

Asked the user upfront how to handle the one piece of this part that
touches infrastructure outside this repo (a real WordPress site) — chose
to build and thoroughly test everything first, then check in again when
ready to verify for real. **That live check has not happened yet.**
Everything below is proven against hand-rolled WordPress function stubs,
not a real WP install.

- **`packages/wordpress-bridge/`** — a separate Composer package (own
  `composer.json`, `vendor/`, `phpunit.xml`, `phpstan.neon`), wired to
  core via a Composer path repository, not a separate git repo.
- **`WordPressBridge`** implements `BridgeInterface` for WordPress:
  `assetUrl()` returns a WP REST URL (not a raw `vendor/` filesystem path
  — real WP hosts commonly block direct `vendor/` web access), served via
  a `rest_pre_serve_request` filter tagging the response rather than
  calling `exit()` inside the route callback (a real mistake caught and
  fixed before it shipped — `exit()` there would have made the method
  untestable in-process). RPC auth is a standard `wp_rest` nonce, checked
  in the route's `permission_callback` and delivered to the client via
  `wp_localize_script()`. `Bridge\RpcHandler` (core, from Part 6) needed
  zero changes to support this.
- **`ReactiphShortcode`** (`[reactiph component="..." prop="value"]`) —
  the Part 7 baseline integration point; a Gutenberg block (also named in
  ADR 0004) is still unbuilt. Coerces string shortcode attributes against
  each matching property's declared type (int/float/bool) via reflection
  before assignment.
- **Testing**: hand-rolled WP function/class stubs in
  `tests/bootstrap.php` (no `wp-phpunit`, no Brain Monkey) — also
  PHPStan's `scanFiles` source, so one file serves both runtime stubbing
  and static-analysis signatures.
- **`examples/wordpress-plugin/`** — a real, activatable WP plugin file
  (`reactiph-demo.php`, its own `composer.json` path-repo'd to both
  `reactiph/wordpress-bridge` and, transitively, `reactiph/reactiph` —
  Composer path repositories aren't transitive, so both had to be
  declared explicitly) registering a `Counter` component via the
  shortcode. This is the concrete artifact for the deferred live check —
  ready to drop into a real `wp-content/plugins/` directory.
- 23 new tests in `packages/wordpress-bridge` (core's own 160 unchanged).
  PHPStan (level 8) and PHP-CS-Fixer both clean in both packages.
- CI (`.github/workflows/ci.yml`) now has a second job,
  `check-wordpress-bridge`, running the same three checks against the new
  package on PHP 8.1 and 8.4.
- Not yet committed as of this status update.

## Next up

**Live verification against a real WordPress site** — the immediate next
step, by the user's own choice, before Part 7 can be called fully done.
Needs: Local by Flywheel's app started (the TAW site,
`~/Local Sites/taw`, wasn't running when last checked — returned 502),
then the plugin symlinked/copied into that site's `wp-content/plugins/`,
activated, and exercised (shortcode renders, click-to-increment still
patches the DOM as in Part 5/6, and the RPC nonce/route genuinely works
against real WP REST dispatch, not just the hand-rolled stubs). Nothing
here touches any TAW git repo — `wp-content/plugins` at that Local site
isn't itself version-controlled.

**After that**, before Part 8:
- The Gutenberg block ADR 0004 also named is still unbuilt — needs
  `packages/runtime-js` to actually have build tooling first (an
  already-tracked open thread since Part 5).
- The "which methods are server-only vs. client-transpiled" question
  (ADR 0018) is still open — this part's demo component doesn't touch
  `$wpdb` or anything else that would force the question for real.

## Remaining parts (unstarted)

8. CLI/dev tooling + docs.

## Open threads / not yet decided

- Template syntax has no control-flow directive (`@foreach`/`@if` or
  similar) for dynamically rendering a variable-length list of children.
  Also gates structural DOM patching (ADR 0017).
- Only one, unnamed default slot exists per component (ADR 0008).
- Setting `hydrationId` on a component whose template isn't a single
  HTML-tag root is currently a silent no-op (ADR 0010).
- No allow-list yet for which public properties should actually reach the
  client as hydration state vs. stay server-only (ADR 0010).
- `packages/runtime-js` has no `package.json`/npm tooling yet — also now
  blocks a Gutenberg block (ADR 0019's Consequences).
- Closures are entirely unimplemented in the transpiler (ADR 0015).
- Event bindings only support `click` in practice (ADR 0016) — small,
  mechanical follow-up, not a design question.
- DOM patching is scoped to text-node content only (ADR 0017).
- Nested-component hydration has a defensive boundary check in
  `hydrate.js`'s marker walker but no real example/test exercises it yet.
- **No mechanism marks a component method as server-only vs.
  client-transpiled** (ADR 0018) — still true after Part 7; no demo
  component built so far has forced the question for real.
- **`RpcHandler` (core) has no authentication/authorization of its own**
  (ADR 0018) — `DefaultBridge`'s example still has none. WordPress's
  `WordPressBridge` now does (a `wp_rest` nonce), so this is resolved for
  the WordPress path specifically, not for `DefaultBridge`/a plain PHP app.
- **Live WordPress verification hasn't happened yet** (ADR 0019) — see
  "Next up". Everything in `packages/wordpress-bridge` is proven against
  hand-rolled stubs, which can drift from real WP behavior in ways a
  stub wouldn't catch (nonce lifecycle, REST dispatch edge cases, filter
  ordering against other plugins).
- A Gutenberg block (ADR 0004/0019) is still unbuilt.
