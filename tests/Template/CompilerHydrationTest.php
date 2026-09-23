<?php

declare(strict_types=1);

namespace Reactiph\Tests\Template;

use PHPUnit\Framework\TestCase;
use Reactiph\Component\BaseComponent;
use Reactiph\Template\Compiler;
use Reactiph\Template\Parser;

/**
 * Verifies the root-tag `data-reactiph-id` marking behavior described in
 * Compiler's docblock and ADR 0010 — kept in its own file from the rest of
 * CompilerTest since it's a distinct concern (hydration wiring, not
 * plain SSR compilation).
 */
final class CompilerHydrationTest extends TestCase
{
    public function testRootTagGetsNoIdAttributeWhenHydrationIdIsNull(): void
    {
        $html = $this->renderWithHydrationId('<div><span>Hi</span></div>', null);

        self::assertSame('<div><span>Hi</span></div>', $html);
    }

    public function testRootTagGetsIdAttributeWhenHydrationIdIsSet(): void
    {
        $html = $this->renderWithHydrationId('<div><span>Hi</span></div>', 'c1');

        self::assertSame('<div data-reactiph-id="c1"><span>Hi</span></div>', $html);
    }

    public function testHydrationIdIsHtmlEscaped(): void
    {
        $html = $this->renderWithHydrationId('<div></div>', '"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
    }

    public function testNestedTagsNeverReceiveTheIdAttribute(): void
    {
        $html = $this->renderWithHydrationId('<div><span>Hi</span></div>', 'c1');

        self::assertStringNotContainsString('<span data-reactiph-id', $html);
    }

    public function testMultiRootTemplateSilentlyIgnoresHydrationId(): void
    {
        // No single HTML-tag root exists (two top-level nodes), so setting
        // hydrationId has no effect anywhere — documented as a known
        // limitation (ADR 0010), not an error, exercised here so a future
        // change to that behavior is a deliberate, visible decision.
        $html = $this->renderWithHydrationId('<span>a</span><span>b</span>', 'c1');

        self::assertStringNotContainsString('data-reactiph-id', $html);
    }

    private function renderWithHydrationId(string $template, ?string $hydrationId): string
    {
        $ast = (new Parser())->parse($template);
        $compiled = (new Compiler())->compile($ast);

        $component = new class () extends BaseComponent {
            public function template(): string
            {
                return '';
            }
        };
        $component->hydrationId = $hydrationId;

        return $compiled->render->call($component, $component);
    }
}
