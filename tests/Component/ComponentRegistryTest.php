<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Reactiph\Component\ComponentRegistry;
use Reactiph\Component\InvalidComponentException;
use Reactiph\Component\UnknownComponentException;
use Reactiph\Tests\Component\Fixtures\GreetingComponent;

final class ComponentRegistryTest extends TestCase
{
    #[After]
    public function resetRegistry(): void
    {
        ComponentRegistry::reset();
    }

    public function testRegisterAndResolve(): void
    {
        ComponentRegistry::register('Greeting', GreetingComponent::class);

        self::assertSame(GreetingComponent::class, ComponentRegistry::resolve('Greeting'));
    }

    public function testResolveThrowsForUnregisteredTag(): void
    {
        try {
            ComponentRegistry::resolve('Nope');
            self::fail('Expected an UnknownComponentException.');
        } catch (UnknownComponentException $e) {
            self::assertSame('component.unregistered', $e->code());
            self::assertSame(['tag' => 'Nope'], $e->context());
            self::assertStringContainsString('ComponentRegistry::register', (string) $e->hint());
        }
    }

    public function testRegisterRejectsClassesNotExtendingBaseComponent(): void
    {
        try {
            ComponentRegistry::register('NotAComponent', \stdClass::class);
            self::fail('Expected an InvalidComponentException.');
        } catch (InvalidComponentException $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e);
            self::assertSame('component.invalid_class', $e->code());
            self::assertSame(['class' => \stdClass::class], $e->context());
        }
    }

    public function testResetClearsRegistrations(): void
    {
        ComponentRegistry::register('Greeting', GreetingComponent::class);
        ComponentRegistry::reset();

        $this->expectException(UnknownComponentException::class);
        ComponentRegistry::resolve('Greeting');
    }
}
