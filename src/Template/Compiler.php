<?php

declare(strict_types=1);

namespace Reactiph\Template;

use Reactiph\Component\BaseComponent;
use Reactiph\Component\ComponentRegistry;
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
 *
 * A `<PascalCase />` tag (see {@see TagNode::isComponentName()}) compiles to
 * a child component lookup via {@see ComponentRegistry} instead of literal
 * HTML: attribute values become props assigned onto the child instance, and
 * the tag's children are pre-rendered (in the parent's variable scope) into
 * the child's `slot` property.
 *
 * When a template's single top-level node is a literal HTML tag (not a
 * component tag), that tag is the compiled closure's "root" and additionally
 * emits `data-reactiph-id="..."` when `$component->hydrationId` is set —
 * this is how a hydration client (see {@see \Reactiph\Runtime\HydrationSerializer})
 * locates the DOM node a server-rendered component owns. A template that
 * doesn't have exactly one HTML-tag root (multiple top-level nodes, or a
 * component tag as the root) compiles the same as before but the id is
 * never emitted anywhere — setting `hydrationId` on such a component is
 * currently a silent no-op, not an error. See ADR 0010.
 */
final class Compiler
{
    private int $componentCounter = 0;

    /**
     * @param Node[] $nodes
     */
    public function compile(array $nodes): \Closure
    {
        $this->componentCounter = 0;

        $source = 'return function (\\' . BaseComponent::class . ' $component): string {'
            . 'extract(get_object_vars($component));'
            . '$__html = "";'
            . $this->compileRootNodes($nodes, '$__html')
            . 'return $__html;'
            . '};';

        $closure = eval($source);
        assert($closure instanceof \Closure);

        return $closure;
    }

    /**
     * @param Node[] $nodes
     */
    private function compileRootNodes(array $nodes, string $bufferVar): string
    {
        if (count($nodes) === 1 && $nodes[0] instanceof TagNode && !TagNode::isComponentName($nodes[0]->name)) {
            return $this->compileHtmlTag($nodes[0], $bufferVar, isRoot: true);
        }

        return $this->compileNodes($nodes, $bufferVar);
    }

    /**
     * @param Node[] $nodes
     */
    private function compileNodes(array $nodes, string $bufferVar): string
    {
        $code = '';

        foreach ($nodes as $node) {
            $code .= $this->compileNode($node, $bufferVar);
        }

        return $code;
    }

    private function compileNode(Node $node, string $bufferVar): string
    {
        return match (true) {
            $node instanceof TextNode => $this->emitLiteral($node->text, $bufferVar),
            $node instanceof ExpressionNode => $this->emitEscaped($node->expression, $bufferVar),
            $node instanceof TagNode => TagNode::isComponentName($node->name)
                ? $this->compileComponentTag($node, $bufferVar)
                : $this->compileHtmlTag($node, $bufferVar),
            default => throw CompilerException::unknownNodeType($node::class),
        };
    }

    private function compileHtmlTag(TagNode $node, string $bufferVar, bool $isRoot = false): string
    {
        $code = $this->emitLiteral('<' . $node->name, $bufferVar);

        foreach ($node->attributes as $name => $value) {
            $code .= $this->emitLiteral(' ' . $name . '="', $bufferVar);
            $code .= $value instanceof ExpressionNode
                ? $this->emitEscaped($value->expression, $bufferVar)
                : $this->emitLiteral($value, $bufferVar);
            $code .= $this->emitLiteral('"', $bufferVar);
        }

        if ($isRoot) {
            $code .= 'if (isset($hydrationId)) {' . "\n";
            $code .= '    ' . $bufferVar . ' .= \' data-reactiph-id="\''
                . ' . htmlspecialchars((string) $hydrationId, ENT_QUOTES, \'UTF-8\') . \'"\';' . "\n";
            $code .= '}' . "\n";
        }

        if ($node->selfClosing) {
            $code .= $this->emitLiteral('>', $bufferVar);

            return $code;
        }

        $code .= $this->emitLiteral('>', $bufferVar);
        $code .= $this->compileNodes($node->children, $bufferVar);
        $code .= $this->emitLiteral('</' . $node->name . '>', $bufferVar);

        return $code;
    }

    private function compileComponentTag(TagNode $node, string $bufferVar): string
    {
        $id = ++$this->componentCounter;
        $propsVar = '$__props' . $id;
        $slotVar = '$__slot' . $id;
        $classVar = '$__class' . $id;
        $childVar = '$__child' . $id;

        $code = $propsVar . ' = [];' . "\n";

        foreach ($node->attributes as $name => $value) {
            $valueExpr = $value instanceof ExpressionNode
                ? $value->expression
                : var_export($value, true);
            $code .= $propsVar . '[' . var_export($name, true) . '] = ' . $valueExpr . ';' . "\n";
        }

        // Slot content is rendered in the *parent's* scope (it can
        // reference the parent's variables/`$this`), then handed to the
        // child as a pre-rendered HTML string.
        $code .= $slotVar . ' = "";' . "\n";
        $code .= $this->compileNodes($node->children, $slotVar);

        $code .= $classVar . ' = \\' . ComponentRegistry::class . '::resolve(' . var_export($node->name, true) . ');' . "\n";
        $code .= $childVar . ' = new ' . $classVar . '();' . "\n";
        $code .= 'foreach (' . $propsVar . ' as $__propName => $__propValue) {' . "\n";
        $code .= '    ' . $childVar . '->{$__propName} = $__propValue;' . "\n";
        $code .= '}' . "\n";
        $code .= $childVar . '->slot = ' . $slotVar . ';' . "\n";
        $code .= $bufferVar . ' .= ' . $childVar . '->render();' . "\n";

        return $code;
    }

    private function emitLiteral(string $text, string $bufferVar): string
    {
        if ($text === '') {
            return '';
        }

        return $bufferVar . ' .= ' . var_export($text, true) . ";\n";
    }

    private function emitEscaped(string $expression, string $bufferVar): string
    {
        return $bufferVar . ' .= htmlspecialchars((string)(' . $expression . '), ENT_QUOTES, \'UTF-8\');' . "\n";
    }
}
