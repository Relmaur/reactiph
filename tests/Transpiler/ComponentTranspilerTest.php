<?php

declare(strict_types=1);

namespace Reactiph\Tests\Transpiler;

use PHPUnit\Framework\TestCase;
use Reactiph\Tests\Transpiler\Fixtures\CounterComponentFixture;
use Reactiph\Tests\Transpiler\Support\NodeRunner;
use Reactiph\Transpiler\ComponentTranspiler;

final class ComponentTranspilerTest extends TestCase
{
    public function testAssemblesOnlyMethodsDeclaredOnTheComponentItself(): void
    {
        $js = (new ComponentTranspiler())->transpileComponent(CounterComponentFixture::class);

        self::assertStringContainsString('window.ReactiphComponents', $js);
        self::assertStringContainsString(var_export(CounterComponentFixture::class, true), $js);
        self::assertStringContainsString("'increment': function increment()", $js);
        // template() and render() are BaseComponent/component-authoring
        // concerns, not client-side reactive logic — never transpiled.
        self::assertStringNotContainsString('"template"', $js);
        self::assertStringNotContainsString('"render"', $js);
    }

    public function testAssembledDefinitionRunsInNodeAndMutatesState(): void
    {
        $js = (new ComponentTranspiler())->transpileComponent(CounterComponentFixture::class);

        $stateAfter = NodeRunner::callRegisteredComponentMethod(
            $js,
            CounterComponentFixture::class,
            'increment',
            ['count' => 4],
        );

        self::assertSame(5, $stateAfter['count']);
    }

    public function testMatchesRealPhpAfterCallingIncrementTwice(): void
    {
        $component = new CounterComponentFixture();
        $component->count = 4;
        $component->increment();
        $component->increment();
        $phpResult = $component->count;

        $js = (new ComponentTranspiler())->transpileComponent(CounterComponentFixture::class);

        $stateAfterFirst = NodeRunner::callRegisteredComponentMethod(
            $js,
            CounterComponentFixture::class,
            'increment',
            ['count' => 4],
        );
        $stateAfterSecond = NodeRunner::callRegisteredComponentMethod(
            $js,
            CounterComponentFixture::class,
            'increment',
            $stateAfterFirst,
        );

        self::assertSame($phpResult, $stateAfterSecond['count']);
    }
}
