<?php

declare(strict_types=1);

namespace Reactiph\Tests\Bridge;

use PHPUnit\Framework\TestCase;
use Reactiph\Bridge\DefaultBridge;
use Reactiph\Bridge\RpcException;
use Reactiph\Tests\Transpiler\Fixtures\CounterComponentFixture;

final class DefaultBridgeTest extends TestCase
{
    public function testAssetUrlIsNamespacedUnderTheBasePath(): void
    {
        $bridge = new DefaultBridge('/reactiph');

        self::assertSame('/reactiph/assets/hydrate.js', $bridge->assetUrl('hydrate.js'));
    }

    public function testRpcEndpointUrlIsNamespacedUnderTheBasePath(): void
    {
        $bridge = new DefaultBridge('/reactiph');

        self::assertSame('/reactiph/rpc', $bridge->rpcEndpointUrl());
    }

    public function testBasePathIsConfigurable(): void
    {
        $bridge = new DefaultBridge('/my-app');

        self::assertSame('/my-app/assets/hydrate.js', $bridge->assetUrl('hydrate.js'));
        self::assertSame('/my-app/rpc', $bridge->rpcEndpointUrl());
    }

    public function testServesTheKnownRuntimeAssetsFromDisk(): void
    {
        $bridge = new DefaultBridge();

        $hydrateJs = $bridge->serveAsset('hydrate.js');
        $phpRuntimeJs = $bridge->serveAsset('php-runtime.js');

        self::assertNotNull($hydrateJs);
        self::assertStringContainsString('ReactiphComponents', (string) $hydrateJs);
        self::assertNotNull($phpRuntimeJs);
        self::assertStringContainsString('__phpBool', (string) $phpRuntimeJs);
    }

    public function testReturnsNullForAnAssetItDoesNotOwn(): void
    {
        $bridge = new DefaultBridge();

        self::assertNull($bridge->serveAsset('../composer.json'));
        self::assertNull($bridge->serveAsset('whatever.js'));
    }

    public function testHandleRpcDelegatesToARealComponentMethodCall(): void
    {
        $bridge = new DefaultBridge();

        $result = $bridge->handleRpc([
            'component' => CounterComponentFixture::class,
            'method' => 'increment',
            'state' => ['count' => 9],
            'args' => [],
        ]);

        self::assertSame(['count' => 10], $result['state']);
    }

    public function testHandleRpcPropagatesRpcExceptions(): void
    {
        $bridge = new DefaultBridge();

        $this->expectException(RpcException::class);

        $bridge->handleRpc(['component' => 'NotARealClass', 'method' => 'x', 'state' => [], 'args' => []]);
    }
}
