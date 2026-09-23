<?php

declare(strict_types=1);

namespace Reactiph\WordPressBridge;

use Reactiph\Component\BaseComponent;
use Reactiph\Runtime\HydrationSerializer;
use Reactiph\Transpiler\ComponentTranspiler;

/**
 * `[reactiph component="Fully\Qualified\ComponentClass" prop="value"]` —
 * renders and hydrates a Reactiph component from post content. Part 7's
 * baseline integration point (ADR 0004 also names a Gutenberg block;
 * deliberately not built this part — a shortcode is simpler and works
 * everywhere a block editor's own JS bundle isn't a prerequisite, see ADR
 * 0019's Consequences).
 *
 * Shortcode attributes are always strings (WordPress's own convention);
 * {@see applyAttributes()} coerces each one against the matching
 * property's *declared* type (`int`, `float`, `bool`) before assigning it,
 * so `count="3"` correctly becomes the int `3`, not the string `"3"` —
 * PHP's typed-property enforcement would otherwise throw a `TypeError` for
 * a scalar-typed property assigned a raw string.
 */
final class ReactiphShortcode
{
    /** @var array<class-string<BaseComponent>, true> */
    private static array $transpiledClasses = [];

    public function __construct(private readonly WordPressBridge $bridge)
    {
    }

    public function register(): void
    {
        add_shortcode('reactiph', [$this, 'render']);
    }

    /**
     * Clears the per-class transpilation cache. A real WordPress request
     * is its own fresh PHP process (no shared state between page loads),
     * so this is never called in production — it exists purely for test
     * isolation, since PHPUnit runs many tests in one process where this
     * class's static cache would otherwise silently persist between them.
     * Mirrors `Component\ComponentRegistry::reset()`'s reason for
     * existing in core.
     */
    public static function reset(): void
    {
        self::$transpiledClasses = [];
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function render(array|string $atts): string
    {
        // Deliberately not shortcode_atts(): that helper only returns keys
        // from a *fixed*, known default set, silently dropping anything
        // else -- but a component's own property names aren't known
        // ahead of time here, so every attribute needs to pass through.
        $atts = is_array($atts) ? $atts : [];
        $componentClass = isset($atts['component']) ? (string) $atts['component'] : '';
        unset($atts['component']);

        if ($componentClass === '' || !class_exists($componentClass) || !is_subclass_of($componentClass, BaseComponent::class)) {
            return $this->renderError($componentClass);
        }

        /** @var class-string<BaseComponent> $componentClass */
        $component = new $componentClass();
        $this->applyAttributes($component, $atts);
        $component->hydrationId = 'reactiph-' . wp_unique_id();

        $html = $component->render();
        $hydrationScript = HydrationSerializer::toScriptTag([HydrationSerializer::payloadFor($component)]);

        $this->bridge->enqueueRuntimeAssets();
        $this->ensureTranspiled($componentClass);

        return $html . $hydrationScript;
    }

    /**
     * @param array<string, string> $atts
     */
    private function applyAttributes(BaseComponent $component, array $atts): void
    {
        $reflection = new \ReflectionClass($component);

        foreach ($atts as $name => $value) {
            if (!$reflection->hasProperty($name)) {
                continue;
            }

            $property = $reflection->getProperty($name);

            if (!$property->isPublic()) {
                continue;
            }

            $component->{$name} = $this->coerce($value, $property->getType());
        }
    }

    private function coerce(string $value, ?\ReflectionType $type): int|float|bool|string
    {
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            default => $value,
        };
    }

    /**
     * @param class-string<BaseComponent> $componentClass
     */
    private function ensureTranspiled(string $componentClass): void
    {
        // Guards against re-emitting the same window.ReactiphComponents[X]
        // assignment for every shortcode instance when a page uses the
        // same component more than once -- harmless if it happened
        // (idempotent overwrite), but wasteful output for no benefit.
        if (isset(self::$transpiledClasses[$componentClass])) {
            return;
        }

        $componentJs = (new ComponentTranspiler())->transpileComponent($componentClass);
        wp_add_inline_script('reactiph-hydrate', $componentJs, 'before');
        self::$transpiledClasses[$componentClass] = true;
    }

    /**
     * AI+human-friendly failure (ADR 0009's ethos, applied to a live page
     * instead of a thrown exception): a visible-but-harmless HTML comment,
     * not a fatal error that would take down an entire page over one bad
     * shortcode attribute.
     */
    private function renderError(string $componentClass): string
    {
        $safeClass = htmlspecialchars($componentClass, ENT_QUOTES, 'UTF-8');

        return '<!-- Reactiph: [reactiph component="' . $safeClass . '"] is not a real class extending BaseComponent. -->';
    }
}
