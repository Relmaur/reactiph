<?php

declare(strict_types=1);

namespace Reactiph\Template;

use Reactiph\Component\BaseComponent;
use Reactiph\Template\Node\ExpressionNode;
use Reactiph\Template\Node\Node;
use Reactiph\Template\Node\TagNode;
use Reactiph\Template\Node\TextNode;

/**
 * Turns a template AST into a PHP render closure: `fn(BaseComponent): string`.
 * Component public properties are extracted into local scope so `{$expr}`
 * can reference them directly (e.g. `{$title}`), and `$this` inside an
 * expression refers to the component — callers must invoke the closure with
 * `Closure::call($component, $component)` rather than a plain call, since the
 * closure is cached and reused across instances of the same component class.
 * Expression output is HTML-escaped; literal template markup is emitted
 * verbatim.
 */
final class Compiler
{
    /**
     * @param Node[] $nodes
     */
    public function compile(array $nodes): \Closure
    {
        $source = 'return function (\\' . BaseComponent::class . ' $component): string {'
            . 'extract(get_object_vars($component));'
            . '$__html = "";'
            . $this->compileNodes($nodes)
            . 'return $__html;'
            . '};';

        $closure = eval($source);
        assert($closure instanceof \Closure);

        return $closure;
    }

    /**
     * @param Node[] $nodes
     */
    private function compileNodes(array $nodes): string
    {
        $code = '';

        foreach ($nodes as $node) {
            $code .= $this->compileNode($node);
        }

        return $code;
    }

    private function compileNode(Node $node): string
    {
        return match (true) {
            $node instanceof TextNode => $this->emitLiteral($node->text),
            $node instanceof ExpressionNode => $this->emitEscaped($node->expression),
            $node instanceof TagNode => $this->compileTag($node),
            default => throw new \LogicException('Unknown node type: ' . $node::class),
        };
    }

    private function compileTag(TagNode $node): string
    {
        $code = $this->emitLiteral('<' . $node->name);

        foreach ($node->attributes as $name => $value) {
            $code .= $this->emitLiteral(' ' . $name . '="');
            $code .= $value instanceof ExpressionNode
                ? $this->emitEscaped($value->expression)
                : $this->emitLiteral($value);
            $code .= $this->emitLiteral('"');
        }

        $code .= $this->emitLiteral('>');
        $code .= $this->compileNodes($node->children);
        $code .= $this->emitLiteral('</' . $node->name . '>');

        return $code;
    }

    private function emitLiteral(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return '$__html .= ' . var_export($text, true) . ";\n";
    }

    private function emitEscaped(string $expression): string
    {
        return '$__html .= htmlspecialchars((string)(' . $expression . '), ENT_QUOTES, \'UTF-8\');' . "\n";
    }
}
