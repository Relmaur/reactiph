<?php

declare(strict_types=1);

namespace Reactiph\Tests\Bridge;

use PHPUnit\Framework\TestCase;
use Reactiph\Bridge\RpcException;
use Reactiph\Bridge\RpcHandler;
use Reactiph\Tests\Bridge\Fixtures\ComponentWithPrivateMethodFixture;
use Reactiph\Tests\Transpiler\Fixtures\CounterComponentFixture;
use stdClass;

final class RpcHandlerTest extends TestCase
{
    public function testCallsARealPhpMethodAndReturnsTheMutatedState(): void
    {
        $result = (new RpcHandler())->handle([
            'component' => CounterComponentFixture::class,
            'method' => 'increment',
            'state' => ['count' => 4],
            'args' => [],
        ]);

        self::assertSame(['count' => 5], $result['state']);
    }

    public function testDefaultsMissingStateAndArgsToEmpty(): void
    {
        $result = (new RpcHandler())->handle([
            'component' => CounterComponentFixture::class,
            'method' => 'increment',
        ]);

        self::assertSame(['count' => 1], $result['state']);
    }

    public function testRejectsAMalformedPayload(): void
    {
        try {
            (new RpcHandler())->handle(['component' => CounterComponentFixture::class]);
            self::fail('Expected an RpcException.');
        } catch (RpcException $e) {
            self::assertSame('bridge.rpc_malformed_payload', $e->code());
        }
    }

    public function testRejectsAComponentClassThatDoesNotExist(): void
    {
        try {
            (new RpcHandler())->handle([
                'component' => 'NotARealClass',
                'method' => 'increment',
                'state' => [],
                'args' => [],
            ]);
            self::fail('Expected an RpcException.');
        } catch (RpcException $e) {
            self::assertSame('bridge.rpc_unknown_component', $e->code());
        }
    }

    public function testRejectsAClassThatIsNotAComponent(): void
    {
        try {
            (new RpcHandler())->handle([
                'component' => stdClass::class,
                'method' => 'increment',
                'state' => [],
                'args' => [],
            ]);
            self::fail('Expected an RpcException.');
        } catch (RpcException $e) {
            self::assertSame('bridge.rpc_unknown_component', $e->code());
        }
    }

    public function testRejectsAnUnknownMethod(): void
    {
        try {
            (new RpcHandler())->handle([
                'component' => CounterComponentFixture::class,
                'method' => 'doesNotExist',
                'state' => [],
                'args' => [],
            ]);
            self::fail('Expected an RpcException.');
        } catch (RpcException $e) {
            self::assertSame('bridge.rpc_unknown_method', $e->code());
        }
    }

    public function testRejectsCallingFrameworkInternalMethods(): void
    {
        // template()/render() ARE declared reachably via reflection, but
        // are framework-internal, never RPC surface (ADR 0018) -- the
        // exact same exclusion ComponentTranspiler applies for client
        // transpilation, shared via OwnMethods.
        try {
            (new RpcHandler())->handle([
                'component' => CounterComponentFixture::class,
                'method' => 'template',
                'state' => [],
                'args' => [],
            ]);
            self::fail('Expected an RpcException.');
        } catch (RpcException $e) {
            self::assertSame('bridge.rpc_unknown_method', $e->code());
        }
    }

    public function testRejectsCallingAPrivateHelperMethod(): void
    {
        try {
            (new RpcHandler())->handle([
                'component' => ComponentWithPrivateMethodFixture::class,
                'method' => 'computeSecret',
                'state' => [],
                'args' => [],
            ]);
            self::fail('Expected an RpcException.');
        } catch (RpcException $e) {
            self::assertSame('bridge.rpc_unknown_method', $e->code());
        }
    }

    public function testRejectsAnUndeclaredStateProperty(): void
    {
        try {
            (new RpcHandler())->handle([
                'component' => CounterComponentFixture::class,
                'method' => 'increment',
                'state' => ['notARealProperty' => 1],
                'args' => [],
            ]);
            self::fail('Expected an RpcException.');
        } catch (RpcException $e) {
            self::assertSame('bridge.rpc_unknown_state_property', $e->code());
        }
    }

    public function testExcludesHydrationPlumbingFromTheReturnedState(): void
    {
        $result = (new RpcHandler())->handle([
            'component' => CounterComponentFixture::class,
            'method' => 'increment',
            'state' => ['count' => 0],
            'args' => [],
        ]);

        self::assertArrayNotHasKey('slot', $result['state']);
        self::assertArrayNotHasKey('hydrationId', $result['state']);
    }
}
