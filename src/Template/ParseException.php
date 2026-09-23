<?php

declare(strict_types=1);

namespace Reactiph\Template;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

/**
 * Thrown by {@see Parser} for any malformed template markup. Always built
 * via one of the named constructors below, never `new ParseException(...)`
 * directly, so every parse failure carries a stable code and — where
 * there's an unambiguous fix — a hint.
 */
final class ParseException extends \RuntimeException implements ReactiphException
{
    use CarriesDiagnostics;

    public static function unexpectedContent(int $offset): self
    {
        $e = new self(sprintf('Unexpected content at offset %d.', $offset));

        return $e->withDiagnostics('template.unexpected_content', ['offset' => $offset]);
    }

    public static function missingClosingTag(string $tagName): self
    {
        $e = new self(sprintf('Missing closing tag for <%s>.', $tagName));

        return $e->withDiagnostics(
            'template.missing_closing_tag',
            ['tag' => $tagName],
            sprintf('Add a matching </%s>, or self-close the tag with />.', $tagName),
        );
    }

    public static function malformedClosingTag(int $offset): self
    {
        $e = new self(sprintf('Malformed closing tag near offset %d.', $offset));

        return $e->withDiagnostics(
            'template.malformed_closing_tag',
            ['offset' => $offset],
            'A closing tag must be "</name>" with no attributes.',
        );
    }

    public static function unexpectedClosingTag(string $tagName): self
    {
        $e = new self(sprintf('Unexpected closing tag </%s> with no matching open tag.', $tagName));

        return $e->withDiagnostics(
            'template.unexpected_closing_tag',
            ['tag' => $tagName],
            sprintf('Remove </%s>, or add a matching opening <%s>.', $tagName, $tagName),
        );
    }

    public static function mismatchedClosingTag(string $expectedTag, string $foundTag): self
    {
        $e = new self(sprintf(
            'Mismatched closing tag: expected </%s>, found </%s>.',
            $expectedTag,
            $foundTag,
        ));

        return $e->withDiagnostics(
            'template.mismatched_closing_tag',
            ['expected' => $expectedTag, 'found' => $foundTag],
            sprintf('Tags must nest properly — close <%s> before closing <%s>.', $foundTag, $expectedTag),
        );
    }

    public static function expectedTagName(int $offset): self
    {
        $e = new self(sprintf('Expected a tag name at offset %d.', $offset));

        return $e->withDiagnostics(
            'template.expected_tag_name',
            ['offset' => $offset],
            'A tag name must start right after "<", e.g. "<div" or "<LikeButton".',
        );
    }

    public static function malformedTag(string $tagName, int $offset): self
    {
        $e = new self(sprintf('Malformed tag <%s at offset %d.', $tagName, $offset));

        return $e->withDiagnostics(
            'template.malformed_tag',
            ['tag' => $tagName, 'offset' => $offset],
            'A tag must end with ">" or "/>" after its attributes.',
        );
    }

    public static function expectedAttributeName(int $offset): self
    {
        $e = new self(sprintf('Expected an attribute name at offset %d.', $offset));

        return $e->withDiagnostics('template.expected_attribute_name', ['offset' => $offset]);
    }

    public static function expectedQuotedAttributeValue(int $offset): self
    {
        $e = new self(sprintf('Expected a quoted attribute value at offset %d.', $offset));

        return $e->withDiagnostics(
            'template.expected_quoted_attribute_value',
            ['offset' => $offset],
            'Attribute values must be wrapped in single or double quotes, e.g. title="{$title}".',
        );
    }

    public static function unterminatedAttributeValue(int $offset): self
    {
        $e = new self(sprintf('Unterminated attribute value starting at offset %d.', $offset));

        return $e->withDiagnostics(
            'template.unterminated_attribute_value',
            ['offset' => $offset],
            'Add the closing quote for this attribute value.',
        );
    }

    public static function unterminatedExpression(int $offset): self
    {
        $e = new self(sprintf('Unterminated expression starting at offset %d.', $offset));

        return $e->withDiagnostics(
            'template.unterminated_expression',
            ['offset' => $offset],
            'Add the closing "}" for this {$expr} interpolation.',
        );
    }

    public static function malformedEventBindingName(int $offset): self
    {
        $e = new self(sprintf('Malformed event binding at offset %d.', $offset));

        return $e->withDiagnostics(
            'template.malformed_event_binding_name',
            ['offset' => $offset],
            'An event binding must be "(name)", e.g. "(click)".',
        );
    }

    public static function expectedEventBindingValue(string $eventName, int $offset): self
    {
        $e = new self(sprintf('Expected "=" after (%s) at offset %d.', $eventName, $offset));

        return $e->withDiagnostics(
            'template.expected_event_binding_value',
            ['event' => $eventName, 'offset' => $offset],
            sprintf('Write (%s)="methodName".', $eventName),
        );
    }

    public static function eventBindingValueMustBeAMethodName(string $eventName, int $offset): self
    {
        $e = new self(sprintf(
            '(%s)="..." must be a plain method name, not a {$expr} expression, at offset %d.',
            $eventName,
            $offset,
        ));

        return $e->withDiagnostics(
            'template.event_binding_value_must_be_method_name',
            ['event' => $eventName, 'offset' => $offset],
            sprintf('Write (%s)="methodName", not (%s)="{$expr}".', $eventName, $eventName),
        );
    }

    public static function eventBindingOnComponentTag(string $tagName, int $offset): self
    {
        $e = new self(sprintf(
            'Event bindings are not supported on component tags (<%s> at offset %d).',
            $tagName,
            $offset,
        ));

        return $e->withDiagnostics(
            'template.event_binding_on_component_tag',
            ['tag' => $tagName, 'offset' => $offset],
            'Event bindings only attach to a real DOM element; put the binding on an HTML tag inside '
                . 'the component instead.',
        );
    }
}
