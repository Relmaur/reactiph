# `Counter` — a real TAW ReactiveMetaBlock

ADR 0021 smoke-test artifact. Copy or symlink this whole folder into a real
TAW theme's `Blocks/` directory as `Blocks/Counter/` — TAW's `BlockLoader`
will auto-discover it with zero `taw-core` changes, the same as any other
`MetaBlock`.

## Required setup in the theme

Copying the folder in isn't enough on its own. The theme's own
`functions.php` (or wherever it boots) needs to register Reactiph's REST
routes — nothing in this package does that for you, deliberately (see
`WordPressBridge::registerRoutes()`'s own docblock; `ReactiveMetaBlock`
only calls `enqueueRuntimeAssets()`, never `registerRoutes()`):

```php
add_action('rest_api_init', function () {
    (new \Reactiph\WordPressBridge\WordPressBridge())->registerRoutes();
});
```

Without this, the block still server-renders and its hydration manifest
still appears in the page — but `hydrate.js`/`php-runtime.js` and the RPC
endpoint both 404, so the Increment button does nothing. That's the kind
of failure that looks like a deep bug but is actually just this one
missing hook — see `docs/gotchas.md`'s "Nothing calls
`WordPressBridge::registerRoutes()` for a TAW site" entry in the core
`reactiph` repo.

You'll also need `reactiph/taw-bridge` (and its own dependencies) on the
theme's own Composer autoloader — this folder's own `require_once` for
`CounterComponent.php` only makes the `Counter` class itself
self-contained, it doesn't vendor Reactiph itself.
