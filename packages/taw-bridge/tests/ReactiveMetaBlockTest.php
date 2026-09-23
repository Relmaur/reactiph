<?php

declare(strict_types=1);

namespace Reactiph\TawBridge\Tests;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Reactiph\TawBridge\ReactiveMetaBlock;
use Reactiph\TawBridge\Tests\Fixtures\TestReactiveBlock;
use TAW\Core\Block\BaseBlock;

final class ReactiveMetaBlockTest extends TestCase
{
    #[Before]
    public function resetWordPressStubs(): void
    {
        reactiph_wp_stub_reset();
        ReactiveMetaBlock::reset();
    }

    public function testIsARealTawMetaBlockAsFarAsBlockLoaderIsConcerned(): void
    {
        // This is the entire point of the design: TAW's own BlockLoader
        // auto-discovers via is_subclass_of($class, MetaBlock::class),
        // and ReactiveMetaBlock extends MetaBlock -- so a real taw-core
        // BlockLoader run against a real theme needs zero changes to
        // pick up a ReactiveMetaBlock. Asserting against the real,
        // path-repo'd taw/core class, not a local stand-in.
        $block = new TestReactiveBlock();

        self::assertInstanceOf(\TAW\Core\Block\MetaBlock::class, $block);
        self::assertInstanceOf(BaseBlock::class, $block);
    }

    public function testRendersTheComponentWithStateMappedFromGetData(): void
    {
        $block = new TestReactiveBlock();

        ob_start();
        $block->render(42);
        $html = ob_get_clean();

        self::assertStringContainsString('<span><!--r0-->4<!--/r0--></span>', $html);
    }

    public function testEmbedsAHydrationManifestKeyedByBlockIdAndPostId(): void
    {
        $block = new TestReactiveBlock();

        ob_start();
        $block->render(42);
        $html = ob_get_clean();

        self::assertStringContainsString('"id":"test-counter-42"', $html);
        self::assertStringContainsString('"count":4', $html);
    }

    public function testEmitsTheTranspiledComponentDefinition(): void
    {
        $block = new TestReactiveBlock();

        ob_start();
        $block->render(42);
        $html = ob_get_clean();

        self::assertStringContainsString('window.ReactiphComponents', $html);
        self::assertStringContainsString("'increment': function increment()", $html);
    }

    public function testOnlyEmitsTheComponentDefinitionOnceAcrossMultipleRenders(): void
    {
        $block = new TestReactiveBlock();

        ob_start();
        $block->render(1);
        $block->render(2);
        $html = ob_get_clean();

        // ComponentTranspiler's own output contains the literal string
        // "window.ReactiphComponents" three times per emission
        // (window.ReactiphComponents = window.ReactiphComponents || {};
        // window.ReactiphComponents[...] = ...) -- counting <script> tags
        // is the reliable way to assert "emitted exactly once".
        self::assertSame(1, substr_count($html, '<script>'));
        // Both posts' own markup and manifests still render, though.
        self::assertStringContainsString('"id":"test-counter-1"', $html);
        self::assertStringContainsString('"id":"test-counter-2"', $html);
    }

    public function testEnqueuesTheSharedRuntimeAssets(): void
    {
        $block = new TestReactiveBlock();

        ob_start();
        $block->render(42);
        ob_get_clean();

        self::assertCount(2, reactiph_wp_stub_calls('wp_enqueue_script'));
    }

    public function testDoesNothingWhenNoPostIdIsAvailable(): void
    {
        // get_the_ID() stub returns false by default (no current post
        // outside a real WP loop) -- must not fatal, just render nothing.
        $block = new TestReactiveBlock();

        ob_start();
        $block->render();
        $html = ob_get_clean();

        self::assertSame('', $html);
    }
}
