<?php

declare(strict_types=1);

namespace Reactiph\Tests\Runtime;

use PHPUnit\Framework\TestCase;
use Reactiph\Runtime\HydrationSerializer;
use Reactiph\Runtime\MissingHydrationIdException;
use Reactiph\Tests\Runtime\Fixtures\CounterFixture;

final class HydrationSerializerTest extends TestCase
{
    public function testPayloadForThrowsWithoutHydrationId(): void
    {
        $component = new CounterFixture();

        try {
            HydrationSerializer::payloadFor($component);
            self::fail('Expected a MissingHydrationIdException.');
        } catch (MissingHydrationIdException $e) {
            self::assertSame('runtime.missing_hydration_id', $e->code());
            self::assertSame(['class' => CounterFixture::class], $e->context());
        }
    }

    public function testPayloadForExcludesInternalProperties(): void
    {
        $component = new CounterFixture();
        $component->count = 5;
        $component->hydrationId = 'c1';
        $component->slot = 'ignored';

        $payload = HydrationSerializer::payloadFor($component);

        self::assertSame('c1', $payload->id);
        self::assertSame(CounterFixture::class, $payload->component);
        self::assertSame(['count' => 5, 'label' => ''], $payload->state);
    }

    public function testToScriptTagProducesValidEmbeddableJson(): void
    {
        $component = new CounterFixture();
        $component->count = 2;
        $component->hydrationId = 'c1';

        $scriptTag = HydrationSerializer::toScriptTag([HydrationSerializer::payloadFor($component)]);

        self::assertStringStartsWith('<script type="application/json" id="reactiph-hydration">', $scriptTag);
        self::assertStringEndsWith('</script>', $scriptTag);

        preg_match('/<script[^>]*>(.*)<\/script>/s', $scriptTag, $matches);
        $decoded = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([
            ['id' => 'c1', 'component' => CounterFixture::class, 'state' => ['count' => 2, 'label' => '']],
        ], $decoded);
    }

    public function testToScriptTagEscapesStateThatWouldBreakOutOfTheScriptTag(): void
    {
        $component = new CounterFixture();
        $component->hydrationId = 'c1';
        $component->count = 0;
        $component->label = '</script><script>alert(1)</script>';

        $scriptTag = HydrationSerializer::toScriptTag([HydrationSerializer::payloadFor($component)]);

        // Exactly one real closing tag — none of the payload content
        // produced a literal "</script>" that could prematurely terminate
        // the tag (see the JSON_HEX_TAG comment in HydrationSerializer).
        self::assertSame(1, substr_count($scriptTag, '</script>'));
    }
}
