<?php

declare(strict_types=1);

namespace Reactiph\Tests\Template;

use PHPUnit\Framework\TestCase;
use Reactiph\Template\Node\ExpressionNode;
use Reactiph\Template\Node\TagNode;
use Reactiph\Template\Node\TextNode;
use Reactiph\Template\ParseException;
use Reactiph\Template\Parser;

final class ParserTest extends TestCase
{
    public function testParsesLiteralText(): void
    {
        $nodes = (new Parser())->parse('hello world');

        self::assertCount(1, $nodes);
        self::assertInstanceOf(TextNode::class, $nodes[0]);
        self::assertSame('hello world', $nodes[0]->text);
    }

    public function testParsesExpression(): void
    {
        $nodes = (new Parser())->parse('{$name}');

        self::assertCount(1, $nodes);
        self::assertInstanceOf(ExpressionNode::class, $nodes[0]);
        self::assertSame('$name', $nodes[0]->expression);
    }

    public function testParsesExpressionWithMethodCallAndNestedBraces(): void
    {
        $nodes = (new Parser())->parse('{$this->items[0]}');

        self::assertInstanceOf(ExpressionNode::class, $nodes[0]);
        self::assertSame('$this->items[0]', $nodes[0]->expression);
    }

    public function testParsesTagWithLiteralAttributeAndChildText(): void
    {
        $nodes = (new Parser())->parse('<div class="greeting">Hi</div>');

        self::assertCount(1, $nodes);
        /** @var TagNode $tag */
        $tag = $nodes[0];
        self::assertInstanceOf(TagNode::class, $tag);
        self::assertSame('div', $tag->name);
        self::assertSame(['class' => 'greeting'], $tag->attributes);
        self::assertCount(1, $tag->children);
        self::assertInstanceOf(TextNode::class, $tag->children[0]);
        self::assertSame('Hi', $tag->children[0]->text);
    }

    public function testParsesExpressionAttribute(): void
    {
        $nodes = (new Parser())->parse('<a href="{$url}"></a>');

        /** @var TagNode $tag */
        $tag = $nodes[0];
        self::assertInstanceOf(ExpressionNode::class, $tag->attributes['href']);
        self::assertSame('$url', $tag->attributes['href']->expression);
    }

    public function testParsesNestedTagsAndMixedContent(): void
    {
        $nodes = (new Parser())->parse('<div><h1>{$title}</h1><p>Body</p></div>');

        /** @var TagNode $div */
        $div = $nodes[0];
        self::assertCount(2, $div->children);

        /** @var TagNode $h1 */
        $h1 = $div->children[0];
        self::assertSame('h1', $h1->name);
        self::assertInstanceOf(ExpressionNode::class, $h1->children[0]);

        /** @var TagNode $p */
        $p = $div->children[1];
        self::assertSame('p', $p->name);
        self::assertSame('Body', $p->children[0]->text);
    }

    public function testParsesSelfClosingTag(): void
    {
        $nodes = (new Parser())->parse('<hr/>');

        self::assertInstanceOf(TagNode::class, $nodes[0]);
        self::assertSame('hr', $nodes[0]->name);
        self::assertSame([], $nodes[0]->children);
        self::assertTrue($nodes[0]->selfClosing);
    }

    public function testTreatsKnownVoidElementsAsSelfClosingWithoutSlash(): void
    {
        $nodes = (new Parser())->parse('<img src="a.png">after');

        self::assertInstanceOf(TagNode::class, $nodes[0]);
        self::assertSame('img', $nodes[0]->name);
        self::assertSame([], $nodes[0]->children);
        self::assertTrue($nodes[0]->selfClosing);
        self::assertInstanceOf(TextNode::class, $nodes[1]);
        self::assertSame('after', $nodes[1]->text);
    }

    public function testExplicitlyEmptyTagIsNotMarkedSelfClosing(): void
    {
        $nodes = (new Parser())->parse('<div></div>');

        self::assertInstanceOf(TagNode::class, $nodes[0]);
        self::assertSame([], $nodes[0]->children);
        self::assertFalse($nodes[0]->selfClosing);
    }

    public function testBooleanAttributeWithoutValue(): void
    {
        $nodes = (new Parser())->parse('<input disabled>');

        self::assertSame(['disabled' => ''], $nodes[0]->attributes);
    }

    public function testThrowsOnMismatchedClosingTag(): void
    {
        $this->expectException(ParseException::class);

        (new Parser())->parse('<div><span></div></span>');
    }

    public function testThrowsOnUnclosedTag(): void
    {
        $this->expectException(ParseException::class);

        (new Parser())->parse('<div><span></div>');
    }

    public function testThrowsOnUnexpectedClosingTagAtTopLevel(): void
    {
        $this->expectException(ParseException::class);

        (new Parser())->parse('</div>');
    }

    public function testThrowsOnUnterminatedExpression(): void
    {
        $this->expectException(ParseException::class);

        (new Parser())->parse('{$name');
    }
}
