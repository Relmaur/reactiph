<?php

declare(strict_types=1);

namespace Reactiph\Tests\Template;

use PHPUnit\Framework\TestCase;
use Reactiph\Component\BaseComponent;
use Reactiph\Template\Compiler;
use Reactiph\Template\Parser;

final class CompilerTest extends TestCase
{
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
