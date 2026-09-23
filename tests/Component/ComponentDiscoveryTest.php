<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Reactiph\Component\ComponentDiscovery;
use Reactiph\Component\ComponentRegistry;
use Reactiph\Component\UnknownComponentException;
use Reactiph\Tests\Component\Fixtures\Discoverable\OneComponent;
use Reactiph\Tests\Component\Fixtures\Discoverable\TwoComponent;

final class ComponentDiscoveryTest extends TestCase
{
    #[Before]
    #[After]
    public function resetRegistry(): void
    {
        ComponentRegistry::reset();
    }

    public function testRegistersEveryConcreteComponentFoundUnderTheDirectoryByItsShortClassName(): void
    {
        ComponentDiscovery::registerDirectory(__DIR__ . '/Fixtures/Discoverable');

        self::assertSame(OneComponent::class, ComponentRegistry::resolve('OneComponent'));
        self::assertSame(TwoComponent::class, ComponentRegistry::resolve('TwoComponent'));
    }

    public function testSkipsAnAbstractComponentClass(): void
    {
        ComponentDiscovery::registerDirectory(__DIR__ . '/Fixtures/Discoverable');

        $this->expectException(UnknownComponentException::class);
        ComponentRegistry::resolve('AbstractComponent');
    }

    public function testSkipsAClassThatDoesNotExtendBaseComponent(): void
    {
        ComponentDiscovery::registerDirectory(__DIR__ . '/Fixtures/Discoverable');

        $this->expectException(UnknownComponentException::class);
        ComponentRegistry::resolve('NotAComponent');
    }

    public function testAMissingDirectoryRegistersNothingRatherThanThrowing(): void
    {
        ComponentDiscovery::registerDirectory(__DIR__ . '/Fixtures/DoesNotExist');

        $this->expectException(UnknownComponentException::class);
        ComponentRegistry::resolve('OneComponent');
    }
}
