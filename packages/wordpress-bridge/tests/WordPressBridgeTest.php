<?php

declare(strict_types=1);

namespace Reactiph\WordPressBridge\Tests;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Reactiph\WordPressBridge\WordPressBridge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class WordPressBridgeTest extends TestCase
{
    #[Before]
    public function resetWordPressStubs(): void
    {
        reactiph_wp_stub_reset();
    }

    public function testAssetUrlIsARestUrlUnderTheReactiphNamespace(): void
    {
        $bridge = new WordPressBridge();

        self::assertSame(
            'https://example.test/wp-json/reactiph/v1/assets/hydrate.js',
            $bridge->assetUrl('hydrate.js'),
        );
    }

    public function testRpcEndpointUrlIsARestUrlUnderTheReactiphNamespace(): void
    {
        $bridge = new WordPressBridge();

        self::assertSame('https://example.test/wp-json/reactiph/v1/rpc', $bridge->rpcEndpointUrl());
    }

    public function testRegisterRoutesRegistersBothRestRoutes(): void
    {
        (new WordPressBridge())->registerRoutes();

        $calls = reactiph_wp_stub_calls('register_rest_route');
        self::assertCount(2, $calls);

        $routes = array_map(static fn (array $call): string => $call[1], $calls);
        self::assertContains('/assets/(?P<name>[\w.\-]+)', $routes);
        self::assertContains('/rpc', $routes);

        foreach ($calls as $call) {
            self::assertSame('reactiph/v1', $call[0]);
        }
    }

    public function testServeAssetRouteReturnsPhpRuntimeJsContentsTaggedAsRaw(): void
    {
        $bridge = new WordPressBridge();
        $request = new WP_REST_Request(['name' => 'php-runtime.js']);

        $result = $bridge->serveAssetRoute($request);

        self::assertInstanceOf(WP_REST_Response::class, $result);
        self::assertStringContainsString('__phpBool', (string) $result->get_data());
        self::assertSame('application/javascript; charset=UTF-8', $result->get_headers()['Content-Type']);
        self::assertSame('1', $result->get_headers()['X-Reactiph-Raw']);
    }

    public function testServePlainTextResponseEchoesAndShortCircuitsForATaggedResponse(): void
    {
        $bridge = new WordPressBridge();
        $response = new WP_REST_Response('// raw js bytes');
        $response->header('Content-Type', 'application/javascript; charset=UTF-8');
        $response->header('X-Reactiph-Raw', '1');
        $request = new WP_REST_Request();

        ob_start();
        $served = $bridge->servePlainTextResponse(false, $response, $request, null);
        $output = ob_get_clean();

        self::assertTrue($served);
        self::assertSame('// raw js bytes', $output);
    }

    public function testServePlainTextResponseLeavesUntaggedResponsesAlone(): void
    {
        $bridge = new WordPressBridge();
        $response = new WP_REST_Response(['state' => ['count' => 1]]);
        $request = new WP_REST_Request();

        ob_start();
        $served = $bridge->servePlainTextResponse(false, $response, $request, null);
        $output = ob_get_clean();

        self::assertFalse($served);
        self::assertSame('', $output);
    }

    public function testServeAssetRouteReturnsAnErrorForAnUnknownAsset(): void
    {
        $bridge = new WordPressBridge();
        $request = new WP_REST_Request(['name' => 'nope.js']);

        $result = $bridge->serveAssetRoute($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('reactiph_unknown_asset', $result->code);
    }

    public function testCheckRpcNonceAcceptsAValidNonce(): void
    {
        $bridge = new WordPressBridge();
        $request = new WP_REST_Request([], ['X-WP-Nonce' => 'a-real-looking-nonce']);

        self::assertTrue($bridge->checkRpcNonce($request));
    }

    public function testCheckRpcNonceRejectsAMissingNonceHeader(): void
    {
        $bridge = new WordPressBridge();
        $request = new WP_REST_Request([], []);

        self::assertFalse($bridge->checkRpcNonce($request));
    }

    public function testCheckRpcNonceRejectsAnInvalidNonce(): void
    {
        $GLOBALS['reactiph_wp_stub_nonce_valid'] = false;

        $bridge = new WordPressBridge();
        $request = new WP_REST_Request([], ['X-WP-Nonce' => 'wrong']);

        self::assertFalse($bridge->checkRpcNonce($request));
    }

    public function testHandleRpcRouteReturnsARestResponseOnSuccess(): void
    {
        $bridge = new WordPressBridge();
        $body = json_encode([
            'component' => Fixtures\CounterFixture::class,
            'method' => 'increment',
            'state' => ['count' => 4],
            'args' => [],
        ], JSON_THROW_ON_ERROR);
        $request = new WP_REST_Request([], [], $body);

        $result = $bridge->handleRpcRoute($request);

        self::assertInstanceOf(WP_REST_Response::class, $result);
        self::assertSame(['state' => ['count' => 5]], $result->get_data());
    }

    public function testHandleRpcRouteReturnsAWpErrorOnAnRpcException(): void
    {
        $bridge = new WordPressBridge();
        $body = json_encode(['component' => 'NotReal', 'method' => 'x'], JSON_THROW_ON_ERROR);
        $request = new WP_REST_Request([], [], $body);

        $result = $bridge->handleRpcRoute($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('bridge.rpc_unknown_component', $result->code);
    }

    public function testEnqueueRuntimeAssetsEnqueuesBothScriptsInDependencyOrder(): void
    {
        (new WordPressBridge())->enqueueRuntimeAssets();

        $calls = reactiph_wp_stub_calls('wp_enqueue_script');
        self::assertCount(2, $calls);
        self::assertSame('reactiph-php-runtime', $calls[0][0]);
        self::assertSame('reactiph-hydrate', $calls[1][0]);
        self::assertSame(['reactiph-php-runtime'], $calls[1][2]);
    }

    public function testEnqueueRuntimeAssetsLocalizesTheRpcUrlAndANonce(): void
    {
        (new WordPressBridge())->enqueueRuntimeAssets();

        $calls = reactiph_wp_stub_calls('wp_localize_script');
        self::assertCount(1, $calls);
        self::assertSame('reactiph-hydrate', $calls[0][0]);
        self::assertSame('ReactiphConfig', $calls[0][1]);
        self::assertArrayHasKey('rpcUrl', $calls[0][2]);
        self::assertArrayHasKey('nonce', $calls[0][2]);
    }
}
