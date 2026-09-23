# Status

Single source of truth for "where are we in the build." Update this at the
end of every part (or session) so the next session — human or agent — can
pick up warm without re-deriving state from git log or conversation
history. See `CLAUDE.md` for the full build order and working agreement.

## Current part

**Part 1 — Scaffold + SSR-only templating: done, verified.**

- `composer.json` (`reactiph/reactiph`, PSR-4 `Reactiph\`), PHPUnit harness.
- `Template\Parser`: tokenizes `<div>{$expr}</div>`-style markup into
  TagNode/TextNode/ExpressionNode. Plain HTML only — no custom component
  tags yet (Part 2). Whole-value expression attributes only
  (`prop="{$expr}"`) — no mixed literal/expression attribute content yet.
- `Template\Compiler`: compiles the AST into a cached-per-class PHP render
  closure. Expression output is HTML-escaped; literal markup is verbatim.
- `Component\BaseComponent`: public properties as state, `template()`
  abstract method, `render()` entry point.
- 19 PHPUnit tests passing (`vendor/bin/phpunit`). Manual smoke test at
  `examples/render.php`.
- Dev tooling: PHPStan (level 8, `composer analyse`) and PHP-CS-Fixer
  (PSR-12 + a few strict/risky rules, `composer cs-check` / `cs-fix`) both
  pass clean on current `src/`. `composer check` runs test + analyse +
  cs-check together. CI (`.github/workflows/ci.yml`) runs the same on push/
  PR against PHP 8.1 and 8.4 — inert until a GitHub remote exists.
- AI-era scaffolding in place: `docs/adr/` (7 ADRs capturing the locked-in
  architecture decisions), `docs/gotchas.md` (implementation pitfalls),
  `docs/STATUS.md` (this file), root `CLAUDE.md` (warm-start orientation
  for future sessions).
- Not yet committed to git (repo initialized, nothing staged/committed —
  commits happen only on explicit user request).

## Next up

**Part 2 — Component tree: custom tags, props, nesting.** Not started.
Needs: a component registry so `<CustomTag />` resolves to a child
component class, props flowing down, slot/children content support. Verify
by rendering a Blog/Thumbnail/LikeButton-style nested example to static
HTML end-to-end.

## Remaining parts (unstarted)

3. Hydration payload + client bootstrap (hand-written JS stub).
4. PHP→JS transpiler MVP — highest risk, timeboxed spike.
5. Reactive client runtime.
6. Bridge abstraction + `DefaultBridge`.
7. `reactiph/wordpress-bridge` package.
8. CLI/dev tooling + docs.

## Open threads / not yet decided

- None currently.
