<?php

declare(strict_types=1);

namespace Reactiph\Component;

/**
 * Maps a custom tag name (`<LikeButton />`) to the component class that
 * renders it. Compiled templates resolve tag names through this registry
 * at render time (see {@see \Reactiph\Template\Compiler}), so components
 * must be registered before any template referencing them is rendered.
 *
 * Deliberately a static, process-global registry rather than an injected
 * instance — the compiled render closures have no constructor to receive
 * one through, and Part 2's scope is single-process SSR. Revisit if a
 * later part needs per-request or per-test registry isolation beyond what
 * {@see self::reset()} provides.
 */
final class ComponentRegistry
{
    /** @var array<string, class-string<BaseComponent>> */
    private static array $components = [];

    /**
     * @param class-string<BaseComponent> $componentClass
     */
    public static function register(string $tagName, string $componentClass): void
    {
        if (!is_subclass_of($componentClass, BaseComponent::class)) {
            throw InvalidComponentException::mustExtendBaseComponent($componentClass);
        }

        self::$components[$tagName] = $componentClass;
    }

    /**
     * @return class-string<BaseComponent>
     */
    public static function resolve(string $tagName): string
    {
        if (!isset(self::$components[$tagName])) {
            throw UnknownComponentException::forTag($tagName);
        }

        return self::$components[$tagName];
    }

    /**
     * Clears all registrations. Intended for test isolation between test
     * cases that register components under the same tag name.
     */
    public static function reset(): void
    {
        self::$components = [];
    }
}
