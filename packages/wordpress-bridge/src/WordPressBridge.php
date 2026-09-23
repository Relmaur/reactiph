<?php

declare(strict_types=1);

namespace Reactiph\WordPressBridge;

use Reactiph\Bridge\BridgeInterface;
use Reactiph\Bridge\RpcException;
use Reactiph\Bridge\RpcHandler;
use Reactiph\Component\BaseComponent;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `BridgeInterface` for WordPress (ADR 0004/0018/0019). Serves both
 * concerns through the WP REST API under the `reactiph/v1` namespace,
 * registered via {@see registerRoutes()} (hook to `rest_api_init`):
 *
 *  - `GET /wp-json/reactiph/v1/assets/{name}` — the runtime JS files
 *    (`php-runtime.js`, `hydrate.js`), read from the `reactiph/reactiph`
 *    Composer package's own `packages/runtime-js/` directory (located via
 *    reflection on a core class, not a hardcoded relative path — this
 *    package is installed as its own vendor directory, at an arbitrary
 *    location relative to core).
 *  - `POST /wp-json/reactiph/v1/rpc` — delegates to the same
 *    host-agnostic {@see RpcHandler} `DefaultBridge` uses, gated by a
 *    `wp_rest` nonce (see ADR 0019) rather than left open as
 *    `DefaultBridge`'s example intentionally was.
 *
 * Deliberately does NOT point `wp_enqueue_script()` at a raw
 * `vendor/reactiph/reactiph/packages/runtime-js/...` filesystem path —
 * many real WordPress hosts block direct web access to `vendor/` (a
 * common, real hardening measure, not a hypothetical one), so `assetUrl()`
 * always returns a REST URL instead, exactly mirroring why
 * `DefaultBridge` (Part 6) doesn't assume a bare PHP app has a working
 * static-file convention either.
 */
final class WordPressBridge implements BridgeInterface
{
    private const NAMESPACE = 'reactiph/v1';

    /**
     * The only files this bridge knows how to serve — the static runtime
     * shipped in core's `packages/runtime-js/`. Per-component JS is
     * intentionally not here; see `BridgeInterface::assetUrl()`'s
     * docblock.
     */
    private const RUNTIME_ASSETS = ['php-runtime.js', 'hydrate.js'];

    private readonly RpcHandler $rpcHandler;

    public function __construct()
    {
        $this->rpcHandler = new RpcHandler();
    }

    public function assetUrl(string $name): string
    {
        return rest_url(self::NAMESPACE . '/assets/' . $name);
    }

    public function rpcEndpointUrl(): string
    {
        return rest_url(self::NAMESPACE . '/rpc');
    }

    public function handleRpc(array $payload): array
    {
        return $this->rpcHandler->handle($payload);
    }

    /**
     * Registers both REST routes plus the `rest_pre_serve_request` filter
     * {@see servePlainTextResponse()} needs — call from a `rest_api_init`
     * hook.
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/assets/(?P<name>[\w.\-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'serveAssetRoute'],
            'permission_callback' => '__return_true',
            'args' => [
                'name' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/rpc', [
            'methods' => 'POST',
            'callback' => [$this, 'handleRpcRoute'],
            'permission_callback' => [$this, 'checkRpcNonce'],
        ]);

        add_filter('rest_pre_serve_request', [$this, 'servePlainTextResponse'], 10, 4);
    }

    /**
     * Enqueues the runtime JS (in dependency order) and localizes the RPC
     * URL + a `wp_rest` nonce onto it — call from a `wp_enqueue_scripts`
     * hook, once per page that uses a Reactiph component. Idempotent
     * (WordPress itself no-ops a duplicate `wp_enqueue_script` handle), so
     * safe to call from multiple call sites (e.g. once per shortcode
     * instance — see {@see ReactiphShortcode}) without double-enqueueing.
     */
    public function enqueueRuntimeAssets(): void
    {
        wp_enqueue_script('reactiph-php-runtime', $this->assetUrl('php-runtime.js'), [], false, true);
        wp_enqueue_script('reactiph-hydrate', $this->assetUrl('hydrate.js'), ['reactiph-php-runtime'], false, true);
        wp_localize_script('reactiph-hydrate', 'ReactiphConfig', [
            'rpcUrl' => $this->rpcEndpointUrl(),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public function serveAssetRoute(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $name = (string) $request->get_param('name');
        $contents = $this->serveAsset($name);

        if ($contents === null) {
            return new WP_Error(
                'reactiph_unknown_asset',
                sprintf('Unknown Reactiph asset "%s".', $name),
                ['status' => 404],
            );
        }

        // Deliberately bypasses the REST API's default JSON envelope via
        // servePlainTextResponse() -- this route's data is a static JS
        // file's real bytes, not a JSON-shaped resource. Marked with a
        // custom header rather than calling exit()/echo directly here,
        // which would make this method untestable in-process and
        // impossible to compose with anything else in the response
        // pipeline.
        $response = new WP_REST_Response($contents);
        $response->header('Content-Type', 'application/javascript; charset=UTF-8');
        $response->header('X-Reactiph-Raw', '1');

        return $response;
    }

    /**
     * `rest_pre_serve_request` filter: short-circuits WP's default
     * `wp_json_encode()`-based response serialization specifically for a
     * response {@see serveAssetRoute()} tagged with the `X-Reactiph-Raw`
     * header, and leaves every other route's response untouched.
     *
     * @param mixed $server WP_REST_Server — untyped since nothing here
     *   uses it; only present because it's part of the filter's real
     *   signature.
     */
    public function servePlainTextResponse(
        bool $served,
        WP_REST_Response $result,
        WP_REST_Request $request,
        mixed $server,
    ): bool {
        $headers = $result->get_headers();

        if (!isset($headers['X-Reactiph-Raw'])) {
            return $served;
        }

        header('Content-Type: ' . $headers['Content-Type']);
        echo $result->get_data();

        return true;
    }

    public function handleRpcRoute(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $payload = json_decode($request->get_body(), true);

        try {
            return new WP_REST_Response($this->handleRpc(is_array($payload) ? $payload : []));
        } catch (RpcException $e) {
            return new WP_Error($e->code(), $e->getMessage(), ['status' => 400, 'reactiph' => $e->jsonSerialize()]);
        }
    }

    /**
     * `permission_callback` for the RPC route — verifies the standard WP
     * REST nonce (the same one `rest_api_init`'s own cookie-auth check
     * uses), passed by the client as an `X-WP-Nonce` header. See ADR 0019
     * for why this, not a bespoke token scheme: it's WordPress's own
     * documented mechanism for exactly this case (a same-origin page's
     * own script calling back into a REST route it doesn't otherwise
     * need to authenticate against).
     */
    public function checkRpcNonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');

        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest') !== false;
    }

    private function serveAsset(string $name): ?string
    {
        if (!in_array($name, self::RUNTIME_ASSETS, true)) {
            return null;
        }

        $file = $this->coreRuntimeAssetsDir() . $name;
        $contents = is_file($file) ? file_get_contents($file) : false;

        return $contents === false ? null : $contents;
    }

    private function coreRuntimeAssetsDir(): string
    {
        $baseComponentFile = (new \ReflectionClass(BaseComponent::class))->getFileName();

        if ($baseComponentFile === false) {
            throw new \RuntimeException('Could not locate the reactiph/reactiph core package on disk.');
        }

        // $baseComponentFile is .../reactiph/src/Component/BaseComponent.php
        // -- three hops up reaches the core package's own root, wherever
        // Composer actually installed it (path repo, real vendor/, etc.).
        return dirname($baseComponentFile, 3) . '/packages/runtime-js/';
    }
}
