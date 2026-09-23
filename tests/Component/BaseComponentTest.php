<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component;

use PHPUnit\Framework\TestCase;
use Reactiph\Component\MissingTemplateFileException;

final class BaseComponentTest extends TestCase
{
    public function testRendersTemplateWithComponentStateAndEscapesUntrustedInput(): void
    {
        $component = new Fixtures\GreetingComponent();
        $component->name = 'World';

        self::assertSame(
            '<div class="greeting"><h1>Hello, World!</h1></div>',
            $component->render()
        );

        $component->name = '<script>';
        self::assertSame(
            '<div class="greeting"><h1>Hello, &lt;script&gt;!</h1></div>',
            $component->render()
        );
    }

    public function testRendererIsCachedPerComponentClass(): void
    {
        $a = new Fixtures\GreetingComponent();
        $a->name = 'A';
        $b = new Fixtures\GreetingComponent();
        $b->name = 'B';

        self::assertSame('<div class="greeting"><h1>Hello, A!</h1></div>', $a->render());
        self::assertSame('<div class="greeting"><h1>Hello, B!</h1></div>', $b->render());
    }

    public function testAComponentThatDoesNotOverrideTemplateLoadsItFromASiblingFile(): void
    {
        $component = new Fixtures\FileTemplateComponent();
        $component->name = 'Reactiph';

        self::assertSame('<p>Hello, Reactiph!</p>', $component->render());
    }

    public function testAComponentWithNeitherATemplateOverrideNorASiblingFileThrows(): void
    {
        $component = new Fixtures\NoTemplateFileComponent();

        try {
            $component->render();
            self::fail('Expected a MissingTemplateFileException.');
        } catch (MissingTemplateFileException $e) {
            self::assertSame('component.missing_template_file', $e->code());
            self::assertStringEndsWith('NoTemplateFileComponent.reactiph.html', (string) $e->context()['expected_path']);
        }
    }
}
