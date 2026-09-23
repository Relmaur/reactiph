<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component;

use PHPUnit\Framework\TestCase;
use Reactiph\Component\OwnMethods;
use Reactiph\Tests\Bridge\Fixtures\ComponentWithPrivateMethodFixture;
use Reactiph\Tests\Transpiler\Fixtures\CounterComponentFixture;

final class OwnMethodsTest extends TestCase
{
    public function testReturnsOwnDeclaredPublicMethods(): void
    {
        $methods = OwnMethods::of(CounterComponentFixture::class);

        self::assertArrayHasKey('increment', $methods);
    }

    public function testExcludesTemplateRenderAndConstruct(): void
    {
        $methods = OwnMethods::of(CounterComponentFixture::class);

        self::assertArrayNotHasKey('template', $methods);
        self::assertArrayNotHasKey('render', $methods);
        self::assertArrayNotHasKey('__construct', $methods);
    }

    public function testExcludesAPrivateHelperDeclaredOnTheComponentItself(): void
    {
        $methods = OwnMethods::of(ComponentWithPrivateMethodFixture::class);

        self::assertArrayHasKey('reveal', $methods);
        self::assertArrayNotHasKey('computeSecret', $methods);
    }
}
