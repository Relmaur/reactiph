<?php

declare(strict_types=1);

namespace Reactiph\WordPressBridge\Tests\Fixtures;

use Reactiph\Component\BaseComponent;

/**
 * Exercises {@see \Reactiph\WordPressBridge\ReactiphShortcode}'s
 * attribute-to-property type coercion — shortcode attributes are always
 * strings, so a typed property assigned one without coercion would throw
 * a TypeError.
 */
final class TypedPropsFixture extends BaseComponent
{
    public int $count = 0;
    public bool $active = false;

    public function template(): string
    {
        return '<div>{$count}</div>';
    }
}
