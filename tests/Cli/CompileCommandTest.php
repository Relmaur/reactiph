<?php

declare(strict_types=1);

namespace Reactiph\Tests\Cli;

use PHPUnit\Framework\TestCase;
use Reactiph\Cli\CompileCommand;
use Reactiph\Cli\UnknownComponentClassException;
use Reactiph\Template\ParseException;
use Reactiph\Tests\Cli\Fixtures\Broken\BrokenWidget;
use Reactiph\Tests\Cli\Fixtures\Simple\Widget;

final class CompileCommandTest extends TestCase
{
    public function testTranspilesAComponentsMethodsAndExpressions(): void
    {
        $js = (new CompileCommand())->run(Widget::class);

        // var_export() of the class name escapes backslashes for PHP's own
        // single-quoted string syntax -- the JS output literally contains
        // that doubled-backslash form, not Widget::class's own single
        // backslashes.
        $escapedClassName = str_replace('\\', '\\\\', Widget::class);
        self::assertStringContainsString("window.ReactiphComponents['{$escapedClassName}']", $js);
        self::assertStringContainsString('increment', $js);
    }

    public function testThrowsForAClassThatDoesNotExist(): void
    {
        $this->expectException(UnknownComponentClassException::class);
        (new CompileCommand())->run('Totally\\Made\\Up\\ClassName');
    }

    public function testThrowsForAClassThatIsNotAComponent(): void
    {
        $this->expectException(UnknownComponentClassException::class);
        (new CompileCommand())->run(self::class);
    }

    public function testSurfacesARealTemplateParseError(): void
    {
        $this->expectException(ParseException::class);
        (new CompileCommand())->run(BrokenWidget::class);
    }
}
