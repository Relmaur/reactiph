# ADR 0004: BridgeInterface as the seam between framework-agnostic core and host frameworks

- Status: accepted (not yet implemented — targets Part 6/7)
- Date: 2026-09-22

## Context

Reactiph's core must have zero WordPress dependency (it should be usable
as a plain Composer package in any PHP app), but WordPress is the
first-party, fast-follow target host — not an afterthought. Two concerns
are inherently host-specific: serving the compiled JS runtime asset, and
exposing an endpoint for server-bound RPC calls (e.g. a component method
invoked from the client). Every host framework does both of these
differently (WordPress: `wp_enqueue_script` + a REST route; a plain PHP
app: a static file + a router endpoint).

## Decision

Define a `BridgeInterface` in `src/Bridge/` abstracting exactly these two
concerns: "serve the JS runtime asset" and "expose an RPC endpoint for
server-bound method calls." Core ships `DefaultBridge`, which implements
this against the PHP built-in server / any PSR-7-ish app (Part 6). A later,
separate `reactiph/wordpress-bridge` Composer package (Part 7) implements
the same interface for WordPress: `wp_enqueue_script`, a
`wp-json/reactiph/v1/rpc` REST route, and a shortcode/Gutenberg block.

## Alternatives considered

- **WordPress support baked into core.** Rejected: violates the
  framework-agnostic requirement and forces every non-WordPress consumer
  to carry WordPress-shaped code paths (or worse, an optional WordPress
  dependency) they'll never use.
- **No first-party WordPress support, leave it to userland.** Rejected:
  the user wants WordPress support as a fast-follow, not skipped — TAW's
  own sites are a real target consumer, and an unofficial/community bridge
  is a worse first experience than an official one.

## Consequences

- `BridgeInterface`'s shape is load-bearing for both `DefaultBridge` (Part
  6) and `reactiph/wordpress-bridge` (Part 7) — changing it after Part 7
  ships means updating two consumers instead of one, so it's worth getting
  the interface right in Part 6 rather than iterating on it later.
- `reactiph/wordpress-bridge` is a **separate Composer package**, not a
  subdirectory shipped inside core — core's `composer.json` must never
  gain a WordPress dependency, even an optional one.
- Part 7's WordPress smoke test is read-only against a real Local by
  Flywheel TAW site — no changes to TAW repos as part of that work.
