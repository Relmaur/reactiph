<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Reactiph\Component\ComponentRegistry;
use Reactiph\Tests\Component\Fixtures\BlogComponent;
use Reactiph\Tests\Component\Fixtures\LikeButtonComponent;
use Reactiph\Tests\Component\Fixtures\ThumbnailComponent;

/**
 * End-to-end Part 2 verification: a Blog component nests two Thumbnail
 * components, each of which nests a LikeButton component, entirely via
 * SSR — the scenario called out in the build plan.
 */
final class ComponentTreeTest extends TestCase
{
    #[Before]
    #[After]
    public function resetRegistry(): void
    {
        ComponentRegistry::reset();
    }

    public function testRendersNestedComponentTreeWithPropsFlowingDown(): void
    {
        ComponentRegistry::register('Thumbnail', ThumbnailComponent::class);
        ComponentRegistry::register('LikeButton', LikeButtonComponent::class);

        $html = (new BlogComponent())->render();

        self::assertSame(
            '<div class="blog">'
            . '<h1>Latest posts</h1>'
            . '<div class="thumbnail"><img src="/img/hello.png"><h3>Hello World</h3>'
            . '<button class="like">4 likes</button></div>'
            . '<div class="thumbnail"><img src="/img/second.png"><h3>Second Post</h3>'
            . '<button class="like">9 likes</button></div>'
            . '</div>',
            $html
        );
    }

    public function testRenderingUnregisteredComponentTagThrows(): void
    {
        ComponentRegistry::register('Thumbnail', ThumbnailComponent::class);
        // LikeButton deliberately left unregistered.

        $this->expectException(\Reactiph\Component\UnknownComponentException::class);

        (new BlogComponent())->render();
    }
}
