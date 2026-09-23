# ADR 0019: `reactiph/wordpress-bridge` — package shape, asset serving, RPC auth, shortcode

- Status: accepted
- Date: 2026-09-22

## Context

ADR 0004 already committed to a separate `reactiph/wordpress-bridge`
Composer package implementing `BridgeInterface` for WordPress
(`wp_enqueue_script`, a REST route, a shortcode/block), and flagged core's
`composer.json` as never allowed to gain a WordPress dependency. What it
didn't settle: where this package actually lives, how it serves static
assets given real WordPress hosting constraints, and how the RPC endpoint
gets authenticated (ADR 0018 explicitly deferred this to "whenever Part 7
needs it for real").

## Decision

**Package location: `packages/wordpress-bridge/`, a sibling to
`packages/runtime-js/`, not a separate git repository.** It has its own
`composer.json` (name `reactiph/wordpress-bridge`, its own
`vendor/`/`composer.lock`, its own `phpunit.xml`/`phpstan.neon`), wired
back to core via a Composer path repository (`"url": "../.."`) rather than
a git dependency — satisfies ADR 0004's "separate Composer package, no WP
dependency in core" requirement without the overhead of a second git repo
this early. `composer install` inside it symlinks in the real
`reactiph/reactiph` core, proving the two packages actually compose rather
than just type-checking against each other's interfaces in isolation.
Splitting it into its own repository later, if ever needed (e.g. for a
real Packagist release), is a low-cost move — the package is already
self-contained.

**Asset serving goes through a WP REST route, not `wp_enqueue_script()`
pointed directly at a `vendor/` filesystem path.** Many real WordPress
hosts block direct web access to `vendor/` (a common hardening measure,
not hypothetical) — pointing `wp_enqueue_script()`'s `$src` at
`vendor/reactiph/reactiph/packages/runtime-js/hydrate.js` would 404 on
exactly those hosts. `WordPressBridge::assetUrl()` always returns a
`rest_url('reactiph/v1/assets/{name}')` instead; `wp_enqueue_script()` is
still used (per ADR 0004), just pointed at that REST URL rather than a raw
path — the same principle `DefaultBridge` (Part 6) already applied for a
bare PHP app with no guaranteed static-file convention of its own, now
applied to WordPress's actual, real constraint instead of a hypothetical
one.

**The asset REST route serves raw bytes via `rest_pre_serve_request`, not
`exit()` inside the callback.** The first draft of `serveAssetRoute()`
called `header()` + `echo` + `exit()` directly — a real design mistake
caught before it shipped: calling `exit()` inside a REST callback makes it
untestable in-process (a PHPUnit test calling it would kill the test
runner) and impossible to compose with anything else in the response
pipeline. Fixed by tagging the `WP_REST_Response` with a custom
`X-Reactiph-Raw` header and handling the actual raw output in a separate
`rest_pre_serve_request` filter method, which only intercepts responses
carrying that header and leaves every other route (including the RPC one)
untouched — a documented, if less common, WP REST pattern for serving
non-JSON content, and one that's directly unit-testable via output
buffering instead of needing process isolation.

**RPC auth: a standard `wp_rest` nonce**, checked in the route's
`permission_callback` via `wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')`
— WordPress's own documented mechanism for exactly this case (a
same-origin page's own script calling back into a REST route). The nonce
is delivered to the client via `wp_localize_script()` alongside the RPC
URL (`window.ReactiphConfig.nonce`/`.rpcUrl`), the same idiomatic
data-to-JS handoff WordPress plugins already use for this purpose.
`Bridge\RpcHandler` itself (core, from Part 6) needed zero changes —
exactly the payoff ADR 0018 anticipated: only the host-specific
request/response marshaling (and now, auth) had to be written per bridge.

**Shortcode, not a Gutenberg block, for Part 7's baseline.**
`[reactiph component="Fully\Qualified\Class" prop="value"]` — simpler,
works in classic and block editors alike, and needs no separate JS build
step of its own (a Gutenberg block does, and would be a second, genuinely
separate scope of work). Explicitly deferred, not forgotten — see
Consequences. Shortcode attributes are always strings; `ReactiphShortcode`
coerces each one against the matching property's *declared* type
(`int`/`float`/`bool`) via reflection before assignment, since PHP's own
typed-property enforcement would otherwise throw a `TypeError` for e.g. a
raw string `"3"` assigned to an `int $count` property.

**No new WordPress test framework dependency.** `packages/wordpress-bridge/tests/bootstrap.php`
hand-rolls stub implementations of the ~12 WP functions/classes actually
used (`rest_url`, `register_rest_route`, `wp_verify_nonce`,
`wp_enqueue_script`, `WP_REST_Request`, ...), each recording its call
arguments for assertions — no `wp-phpunit`, no Brain Monkey. The same file
is also PHPStan's `scanFiles` source for `src/`'s static analysis (real
signatures serving both purposes, not two independently-maintained
copies), matching the project's established preference for a light
footprint over a heavy dependency (see `NodeRunner` in core's own test
suite).

**The live check against a real WordPress install is deliberately
deferred, not skipped.** Asked the user how to handle it, since it's the
first thing this session touching infrastructure outside this repo (the
TAW Local by Flywheel site) — chose to build and thoroughly unit-test
everything first, then check in again once ready to verify against the
real site (which also needs Local's own app started; it wasn't running
when checked). `examples/wordpress-plugin/` is the concrete artifact for
that eventual check: a real, activatable WP plugin file
(`reactiph-demo.php`) with its own `composer.json` (path-repo'd to both
`reactiph/wordpress-bridge` and, transitively, `reactiph/reactiph` — note
path repositories aren't transitive in Composer, so this package's
`composer.json` had to declare *both* path entries itself, not rely on
`wordpress-bridge`'s own repositories list being inherited) — registering
a `Counter` component via the shortcode, ready to be dropped into
`wp-content/plugins/` when the user wants to proceed.

## Alternatives considered

- **A fully separate git repository for `wordpress-bridge`.** Rejected
  for now: more process overhead (a second repo to clone, push, and keep
  in sync) for no benefit until an actual external release is needed —
  the path-repository approach gets the "separate Composer package"
  property ADR 0004 asked for without it.
- **`plugins_url()` pointing at a real filesystem path under `vendor/`.**
  Rejected: breaks on real hosts that block direct `vendor/` access — not
  a hypothetical, a common WordPress hardening practice.
- **A WordPress rewrite-rule endpoint instead of a REST route for asset
  serving.** Rejected: requires `flush_rewrite_rules()` on
  activation/deactivation (extra plugin-lifecycle complexity for two
  static files), where a REST route self-registers on `rest_api_init`
  with no such step.
- **`php-stubs/wordpress-stubs` for PHPStan** instead of hand-rolled
  stubs. Considered — it's the standard, comprehensive community package
  for this. Passed over for this package's small actual surface (~12
  symbols): the hand-rolled `tests/bootstrap.php` already has to exist for
  runtime test stubbing, and reusing it for `scanFiles` avoids a second,
  much heavier dependency for signatures this package barely touches.
  Worth revisiting if the package's WP surface grows substantially.

## Consequences

- **A Gutenberg block is still unbuilt** — ADR 0004 named it alongside
  the shortcode; only the shortcode exists as of this part. A block would
  need its own JS build tooling (`packages/runtime-js` has none yet — a
  known open thread since Part 5), making it a genuinely separate piece
  of work, not a quick follow-on.
- **The "which methods are server-only" question (ADR 0018) is still
  open** — this part's own demo component (`Counter`, in
  `examples/wordpress-plugin/`) does plain arithmetic, same as Parts 5/6's
  demos, not a real `$wpdb` call. Part 7 as built didn't force this
  decision the way ADR 0018 anticipated; it remains a real open item for
  whenever a genuinely database-touching component gets built.
- **No live verification against a real WordPress install has happened
  yet** — everything here is proven against hand-rolled stubs standing in
  for WordPress's actual runtime behavior. Stubs can drift from real WP
  behavior in ways a real site would catch and a stub wouldn't (nonce
  lifecycle specifics, REST dispatch edge cases, `rest_pre_serve_request`
  filter ordering against other plugins). This is a real, acknowledged gap
  until the deferred live check happens, not a completed verification —
  `docs/STATUS.md` tracks it as the immediate next step.
- `examples/wordpress-plugin/`'s `composer.json` needed its own two path
  repository entries (for both `reactiph/wordpress-bridge` and
  transitively `reactiph/reactiph`) because Composer path repositories are
  not transitive — a real, easy-to-miss Composer behavior worth
  remembering if this package structure grows another layer.
