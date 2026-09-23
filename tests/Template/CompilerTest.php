<?php

declare(strict_types=1);

namespace Reactiph\Tests\Template;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Reactiph\Component\BaseComponent;
use Reactiph\Component\ComponentRegistry;
use Reactiph\Template\Compiler;
use Reactiph\Template\Parser;
use Reactiph\Tests\Component\Fixtures\CardComponent;
use Reactiph\Tests\Component\Fixtures\LikeButtonComponent;

final class CompilerTest extends TestCase
{
    #[Before]
    #[After]
    public function resetRegistry(): void
    {
        ComponentRegistry::reset();
    }

    public function testCompilesLiteralTextVerbatim(): void
    {
        $html = $this->render('<p>Hello</p>', []);

        self::assertSame('<p>Hello</p>', $html);
    }

    public function testCompilesExpressionAndEscapesOutput(): void
    {
        $html = $this->render('<p>{$name}</p>', ['name' => '<script>alert(1)</script>']);

        self::assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
    }

    public function testVoidElementDoesNotEmitAClosingTag(): void
    {
        $html = $this->render('<img src="a.png">after', []);

        self::assertSame('<img src="a.png">after', $html);
    }

    public function testExplicitlyEmptyTagStillEmitsItsClosingTag(): void
    {
        $html = $this->render('<div></div>', []);

        self::assertSame('<div></div>', $html);
    }

    public function testCompilesExpressionAttributeAndEscapesOutput(): void
    {
        $html = $this->render('<a href="{$url}"></a>', ['url' => 'a"b']);

        self::assertSame('<a href="a&quot;b"></a>', $html);
    }

    public function testMethodCallsAreAvailableViaThis(): void
    {
        $component = new class () extends BaseComponent {
            public function template(): string
            {
                return '<p>{$this->shout()}</p>';
            }

            public function shout(): string
            {
                return 'hi';
            }
        };

        self::assertSame('<p>hi</p>', $component->render());
    }

    public function testComponentTagResolvesChildAndPassesPropsDown(): void
    {
        ComponentRegistry::register('LikeButton', LikeButtonComponent::class);

        $html = $this->render('<LikeButton count="{$likes}" />', ['likes' => 7]);

        self::assertSame('<button class="like">7 likes</button>', $html);
    }

    public function testComponentTagSlotContentIsRenderedInParentScope(): void
    {
        ComponentRegistry::register('Card', CardComponent::class);

        $html = $this->render('<Card heading="Notice">Hello, {$name}!</Card>', ['name' => 'World']);

        self::assertSame(
            '<div class="card"><h2>Notice</h2><div class="card-body">Hello, World!</div></div>',
            $html
        );
    }

    public function testSelfClosingComponentTagGetsEmptySlot(): void
    {
        ComponentRegistry::register('Card', CardComponent::class);

        $html = $this->render('<Card heading="Empty" />', []);

        self::assertSame('<div class="card"><h2>Empty</h2><div class="card-body"></div></div>', $html);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(string $template, array $props): string
    {
        $ast = (new Parser())->parse($template);
        $renderer = (new Compiler())->compile($ast);

        $component = new #[\AllowDynamicProperties] class ($props) extends BaseComponent {
            public function __construct(array $props)
            {
                foreach ($props as $key => $value) {
                    $this->{$key} = $value;
                }
            }

            public function template(): string
            {
                return '';
            }
        };

        return $renderer->call($component, $component);
    }
}
