<?php

declare(strict_types=1);

namespace Reactiph\WordPressBridge\Tests;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Reactiph\WordPressBridge\ReactiphShortcode;
use Reactiph\WordPressBridge\Tests\Fixtures\CounterFixture;
use Reactiph\WordPressBridge\Tests\Fixtures\TypedPropsFixture;
use Reactiph\WordPressBridge\WordPressBridge;

final class ReactiphShortcodeTest extends TestCase
{
    #[Before]
    public function resetWordPressStubs(): void
    {
        reactiph_wp_stub_reset();
        ReactiphShortcode::reset();
    }

    public function testRegisterAddsTheReactiphShortcode(): void
    {
        (new ReactiphShortcode(new WordPressBridge()))->register();

        $calls = reactiph_wp_stub_calls('add_shortcode');
        self::assertCount(1, $calls);
        self::assertSame('reactiph', $calls[0][0]);
    }

    public function testRendersAComponentAndEmbedsAHydrationManifest(): void
    {
        $shortcode = new ReactiphShortcode(new WordPressBridge());

        $html = $shortcode->render(['component' => CounterFixture::class, 'count' => '3']);

        self::assertStringContainsString('<!--r0-->3<!--/r0-->', $html);
        self::assertStringContainsString('reactiph-hydration', $html);
        self::assertStringContainsString('"count":3', $html);
    }

    public function testCoercesAStringAttributeToTheDeclaredIntPropertyType(): void
    {
        $shortcode = new ReactiphShortcode(new WordPressBridge());

        $html = $shortcode->render(['component' => TypedPropsFixture::class, 'count' => '7']);

        // A raw string "7" assigned to an `int` typed property would
        // throw a TypeError -- rendering succeeding at all, with the
        // right value serialized as a JSON number (not "7"), is the
        // proof coercion happened.
        self::assertStringContainsString('"count":7', $html);
    }

    public function testCoercesAStringAttributeToTheDeclaredBoolPropertyType(): void
    {
        $shortcode = new ReactiphShortcode(new WordPressBridge());

        $html = $shortcode->render(['component' => TypedPropsFixture::class, 'active' => 'true']);

        self::assertStringContainsString('"active":true', $html);
    }

    public function testIgnoresAnAttributeThatDoesNotMatchAnyDeclaredProperty(): void
    {
        $shortcode = new ReactiphShortcode(new WordPressBridge());

        // Must not throw for an unknown attribute -- silently ignored,
        // same spirit as an HTML element ignoring an attribute it
        // doesn't recognize.
        $html = $shortcode->render(['component' => CounterFixture::class, 'notAProperty' => 'x']);

        self::assertStringContainsString('reactiph-hydration', $html);
    }

    public function testReturnsAnHtmlCommentForAClassThatIsNotAComponent(): void
    {
        $shortcode = new ReactiphShortcode(new WordPressBridge());

        $html = $shortcode->render(['component' => 'NotARealClass']);

        self::assertStringStartsWith('<!--', $html);
        self::assertStringNotContainsString('reactiph-hydration', $html);
    }

    public function testReturnsAnHtmlCommentForAMissingComponentAttribute(): void
    {
        $shortcode = new ReactiphShortcode(new WordPressBridge());

        $html = $shortcode->render([]);

        self::assertStringStartsWith('<!--', $html);
    }

    public function testEnqueuesRuntimeAssetsOnRender(): void
    {
        $shortcode = new ReactiphShortcode(new WordPressBridge());

        $shortcode->render(['component' => CounterFixture::class]);

        self::assertCount(2, reactiph_wp_stub_calls('wp_enqueue_script'));
    }

    public function testOnlyTranspilesEachComponentClassOnceAcrossMultipleShortcodeInstances(): void
    {
        $shortcode = new ReactiphShortcode(new WordPressBridge());

        $shortcode->render(['component' => CounterFixture::class]);
        $shortcode->render(['component' => CounterFixture::class]);

        self::assertCount(1, reactiph_wp_stub_calls('wp_add_inline_script'));
    }
}
