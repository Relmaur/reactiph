<?php

declare(strict_types=1);

namespace Reactiph\TawBridge;

use Reactiph\Component\BaseComponent;
use Reactiph\Runtime\HydrationSerializer;
use Reactiph\Transpiler\ComponentTranspiler;
use Reactiph\WordPressBridge\WordPressBridge;
use TAW\Core\Block\MetaBlock;
use TAW\Core\Editor\VisualEditor;

/**
 * A TAW MetaBlock whose markup and client-side reactivity come from a
 * Reactiph component instead of a hand-written `index.php` template — ADR
 * 0021. Authored exactly like any other MetaBlock (its own folder,
 * `registerMetaboxes()`, `getData()`) and auto-discovered by TAW's own
 * `BlockLoader` with zero changes to `taw-core`, since this class still
 * *is* a `MetaBlock` as far as `is_subclass_of()` is concerned.
 *
 * There is deliberately no new `BridgeInterface` implementation here — a
 * TAW site is a real WordPress site, so {@see WordPressBridge}'s asset
 * and RPC mechanics already apply unchanged; the actual integration work
 * is entirely at this authoring layer, not the transport layer.
 *
 * A `MetaBlock` instance is long-lived: one per declared variation,
 * created once at boot and registered in `BlockRegistry`, with
 * `render($postId)` called repeatedly for different posts against post
 * meta gathered fresh each time via `getData()`. This doesn't match
 * Reactiph's own model, where state lives on a single component
 * instance's own properties. `render()` here resolves that by
 * constructing a **fresh** Reactiph component instance on every call —
 * this class is an adapter/factory, never itself a `BaseComponent`.
 */
abstract class ReactiveMetaBlock extends MetaBlock
{
    /**
     * Emitting the same `window.ReactiphComponents[X] = {...}` assignment
     * once is enough even if this block (or another using the same
     * component class) renders more than once on a page — mirrors
     * `ReactiphShortcode::$transpiledClasses` in `reactiph/wordpress-bridge`.
     *
     * @var array<class-string<BaseComponent>, true>
     */
    private static array $transpiledClasses = [];

    /**
     * The Reactiph component class this block renders and hydrates.
     *
     * @return class-string<BaseComponent>
     */
    abstract protected function componentClass(): string;

    /**
     * Clears the per-class transpilation cache. A real WordPress request
     * is its own fresh PHP process, so this is never called in
     * production — it exists purely for test isolation, mirroring
     * `ReactiphShortcode::reset()` in `reactiph/wordpress-bridge` for the
     * exact same reason: PHPUnit runs many tests in one process where
     * this class's static cache would otherwise silently persist between
     * them.
     */
    public static function reset(): void
    {
        self::$transpiledClasses = [];
    }

    /**
     * Maps this block's `getData()` array onto the component instance's
     * public properties before it renders — every key matching an
     * actually-declared public property is assigned as-is. Unlike
     * `ReactiphShortcode`'s string-attribute coercion, TAW's metabox
     * engine already returns correctly-typed values (an `int` field
     * really is an `int`), so no type coercion is attempted by default
     * here. Override for anything needing real transformation (e.g. a
     * repeater's array shape differing from the component's own).
     *
     * `$data` is typed as `array<array-key, mixed>`, not `array<string,
     * mixed>` — it comes from an arbitrary block author's own `getData()`
     * override, which `MetaBlock` itself only constrains to `array`, so
     * an int key is a real possibility to guard against here, not a
     * type-checker technicality.
     *
     * @param array<array-key, mixed> $data
     */
    protected function applyState(BaseComponent $component, array $data): void
    {
        $reflection = new \ReflectionClass($component);

        foreach ($data as $key => $value) {
            if (!is_string($key) || !$reflection->hasProperty($key)) {
                continue;
            }

            if ($reflection->getProperty($key)->isPublic()) {
                $component->{$key} = $value;
            }
        }
    }

    /**
     * Mirrors `MetaBlock::render()`'s own visual-editor wrapper exactly —
     * a Reactive block still needs `data-taw-block-section` present when
     * the visual editor is active, or TAW's editor silently can't find or
     * highlight it, the same as any other block.
     */
    public function render(?int $postId = null): void
    {
        $postId = $postId !== null ? $postId : get_the_ID();

        if (!$postId) {
            return;
        }

        if (VisualEditor::isActive()) {
            echo '<div data-taw-block-section="' . esc_attr($this->id) . '">';
            $this->renderComponent($postId);
            echo '</div>';
        } else {
            $this->renderComponent($postId);
        }
    }

    private function renderComponent(int $postId): void
    {
        $data = $this->getData($postId);
        $componentClass = $this->componentClass();

        /** @var BaseComponent $component */
        $component = new $componentClass();
        $this->applyState($component, $data);
        $component->hydrationId = $this->getId() . '-' . $postId;

        (new WordPressBridge())->enqueueRuntimeAssets();

        echo $component->render();
        echo HydrationSerializer::toScriptTag([HydrationSerializer::payloadFor($component)]);
        $this->emitComponentJs($componentClass);
    }

    /**
     * @param class-string<BaseComponent> $componentClass
     */
    private function emitComponentJs(string $componentClass): void
    {
        if (isset(self::$transpiledClasses[$componentClass])) {
            return;
        }

        echo '<script>' . (new ComponentTranspiler())->transpileComponent($componentClass) . '</script>';
        self::$transpiledClasses[$componentClass] = true;
    }
}
