<?php

declare(strict_types=1);

namespace Reactiph\Tests\Template;

use PHPUnit\Framework\TestCase;
use Reactiph\Exception\ReactiphException;
use Reactiph\Template\ParseException;
use Reactiph\Template\Parser;

/**
 * Verifies the diagnostics contract (code/hint/context/JSON) that every
 * Reactiph exception carries, using ParseException as the representative
 * case. See docs/adr/0009-*.md.
 */
final class ParseExceptionTest extends TestCase
{
    public function testImplementsReactiphException(): void
    {
        $e = ParseException::missingClosingTag('div');

        self::assertInstanceOf(ReactiphException::class, $e);
    }

    public function testCarriesStableCodeHintAndContext(): void
    {
        $e = ParseException::missingClosingTag('div');

        self::assertSame('template.missing_closing_tag', $e->code());
        self::assertSame(['tag' => 'div'], $e->context());
        self::assertStringContainsString('</div>', (string) $e->hint());
    }

    public function testJsonSerializeExposesTheSameDiagnostics(): void
    {
        $e = ParseException::mismatchedClosingTag('div', 'span');

        self::assertSame(
            [
                'code' => 'template.mismatched_closing_tag',
                'message' => $e->getMessage(),
                'hint' => $e->hint(),
                'context' => ['expected' => 'div', 'found' => 'span'],
            ],
            $e->jsonSerialize()
        );
        self::assertJson((string) json_encode($e));
    }

    public function testRealParseFailureCarriesDiagnostics(): void
    {
        try {
            (new Parser())->parse('<div><span></div>');
            self::fail('Expected a ParseException.');
        } catch (ParseException $e) {
            self::assertSame('template.mismatched_closing_tag', $e->code());
            self::assertSame(['expected' => 'span', 'found' => 'div'], $e->context());
        }
    }
}
