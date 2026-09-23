<?php

declare(strict_types=1);

namespace Reactiph\Template;

use Reactiph\Template\Node\ExpressionNode;
use Reactiph\Template\Node\Node;
use Reactiph\Template\Node\TagNode;
use Reactiph\Template\Node\TextNode;

/**
 * Tokenizes `<div>{$expr}</div>`-style markup into an AST of
 * TagNode/TextNode/ExpressionNode. Plain HTML only in this part — no custom
 * component tags yet (that's Part 2).
 */
final class Parser
{
    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    private string $source;
    private int $length;
    private int $pos = 0;

    /**
     * @return Node[]
     */
    public function parse(string $template): array
    {
        $this->source = $template;
        $this->length = strlen($template);
        $this->pos = 0;

        $nodes = $this->parseNodes(null);

        if ($this->pos < $this->length) {
            throw new ParseException(sprintf(
                'Unexpected content at offset %d.',
                $this->pos
            ));
        }

        return $nodes;
    }

    /**
     * @return Node[]
     */
    private function parseNodes(?string $closingTag): array
    {
        $nodes = [];
        $foundClosingTag = false;

        while ($this->pos < $this->length) {
            if ($this->matchClosingTag($closingTag)) {
                $foundClosingTag = true;
                break;
            }

            $nodes[] = $this->peek() === '<'
                ? $this->parseTag()
                : $this->parseTextOrExpression();
        }

        if ($closingTag !== null && !$foundClosingTag) {
            throw new ParseException(sprintf('Missing closing tag for <%s>.', $closingTag));
        }

        return $nodes;
    }

    private function matchClosingTag(?string $closingTag): bool
    {
        if (!$this->lookingAt('</')) {
            return false;
        }

        $save = $this->pos;
        $this->pos += 2;
        $name = $this->consumeTagName();
        $this->skipWhitespace();

        if ($this->peek() !== '>') {
            throw new ParseException(sprintf('Malformed closing tag near offset %d.', $save));
        }

        if ($closingTag === null) {
            throw new ParseException(sprintf('Unexpected closing tag </%s> with no matching open tag.', $name));
        }

        if (strcasecmp($name, $closingTag) !== 0) {
            throw new ParseException(sprintf('Mismatched closing tag: expected </%s>, found </%s>.', $closingTag, $name));
        }

        $this->pos++; // consume '>'
        return true;
    }

    private function parseTag(): TagNode
    {
        $start = $this->pos;
        $this->pos++; // consume '<'
        $name = $this->consumeTagName();

        if ($name === '') {
            throw new ParseException(sprintf('Expected tag name at offset %d.', $start));
        }

        $attributes = $this->parseAttributes();
        $this->skipWhitespace();

        if ($this->lookingAt('/>')) {
            $this->pos += 2;
            return new TagNode($name, $attributes, []);
        }

        if ($this->peek() === '>') {
            $this->pos++;

            if (in_array(strtolower($name), self::VOID_ELEMENTS, true)) {
                return new TagNode($name, $attributes, []);
            }

            return new TagNode($name, $attributes, $this->parseNodes($name));
        }

        throw new ParseException(sprintf('Malformed tag <%s at offset %d.', $name, $start));
    }

    /**
     * @return array<string, string|ExpressionNode>
     */
    private function parseAttributes(): array
    {
        $attributes = [];

        while (true) {
            $this->skipWhitespace();
            $char = $this->peek();

            if ($char === null || $char === '/' || $char === '>') {
                break;
            }

            $name = $this->consumeAttributeName();

            if ($name === '') {
                throw new ParseException(sprintf('Expected attribute name at offset %d.', $this->pos));
            }

            $this->skipWhitespace();

            if ($this->peek() === '=') {
                $this->pos++;
                $this->skipWhitespace();
                $attributes[$name] = $this->parseAttributeValue();
            } else {
                $attributes[$name] = '';
            }
        }

        return $attributes;
    }

    private function parseAttributeValue(): string|ExpressionNode
    {
        $quote = $this->peek();

        if ($quote !== '"' && $quote !== "'") {
            throw new ParseException(sprintf('Expected quoted attribute value at offset %d.', $this->pos));
        }

        $this->pos++;
        $start = $this->pos;
        $closing = strpos($this->source, $quote, $this->pos);

        if ($closing === false) {
            throw new ParseException(sprintf('Unterminated attribute value starting at offset %d.', $start));
        }

        $value = substr($this->source, $start, $closing - $start);
        $this->pos = $closing + 1;

        if (preg_match('/^\{(\$.*)\}$/s', $value, $matches) === 1) {
            return new ExpressionNode(trim($matches[1]));
        }

        return $value;
    }

    private function parseTextOrExpression(): Node
    {
        if ($this->lookingAt('{$')) {
            return $this->parseExpression();
        }

        $text = '';

        while ($this->pos < $this->length && $this->peek() !== '<' && !$this->lookingAt('{$')) {
            $text .= $this->source[$this->pos];
            $this->pos++;
        }

        return new TextNode($text);
    }

    private function parseExpression(): ExpressionNode
    {
        $start = $this->pos;
        $this->pos += 2; // consume '{$'
        $expr = '$';
        $depth = 1;

        while ($this->pos < $this->length) {
            $ch = $this->source[$this->pos];

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;

                if ($depth === 0) {
                    $this->pos++; // consume closing '}'
                    return new ExpressionNode(trim($expr));
                }
            }

            $expr .= $ch;
            $this->pos++;
        }

        throw new ParseException(sprintf('Unterminated expression starting at offset %d.', $start));
    }

    private function consumeTagName(): string
    {
        return $this->consumeWhile('/^[a-zA-Z][a-zA-Z0-9:_-]*/');
    }

    private function consumeAttributeName(): string
    {
        return $this->consumeWhile('/^[a-zA-Z_:][a-zA-Z0-9:_.-]*/');
    }

    private function consumeWhile(string $pattern): string
    {
        $rest = substr($this->source, $this->pos);

        if (preg_match($pattern, $rest, $matches) === 1) {
            $this->pos += strlen($matches[0]);
            return $matches[0];
        }

        return '';
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->length && ctype_space($this->source[$this->pos])) {
            $this->pos++;
        }
    }

    private function peek(): ?string
    {
        return $this->pos < $this->length ? $this->source[$this->pos] : null;
    }

    private function lookingAt(string $needle): bool
    {
        return substr($this->source, $this->pos, strlen($needle)) === $needle;
    }
}
