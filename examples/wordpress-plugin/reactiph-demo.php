<?php

/**
 * Plugin Name: Reactiph Bridge Demo
 * Description: Part 7 smoke-test plugin (ADR 0019) -- registers a Counter component via the [reactiph] shortcode, proving WordPressBridge/ReactiphShortcode work against a real WordPress install, not just unit tests against hand-rolled stubs.
 * Version: 0.1.0
 * License: MIT
 *
 * Not published anywhere -- a throwaway verification artifact for this
 * one repo. To use: `composer install` in this directory (resolves
 * reactiph/wordpress-bridge and reactiph/reactiph via the path
 * repositories in composer.json, symlinked from this repo -- no
 * publish/tag step needed), then place or symlink this whole directory
 * into a WordPress install's wp-content/plugins/ and activate it. Add
 * `[reactiph component="ReactiphDemo\\Counter"]` to any post/page.
 */

declare(strict_types=1);

namespace ReactiphDemo;

if (!defined('ABSPATH')) {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

use Reactiph\Component\BaseComponent;
use Reactiph\WordPressBridge\ReactiphShortcode;
use Reactiph\WordPressBridge\WordPressBridge;

final class Counter extends BaseComponent
{
    public int $count = 0;

    public function template(): string
    {
        return <<<'HTML'
<div class="reactiph-counter"><span class="count">{$count}</span><button type="button" (click)="increment">Increment</button></div>
HTML;
    }

    public function increment(): void
    {
        $this->count = $this->count + 1;
    }
}

add_action('rest_api_init', static function (): void {
    (new WordPressBridge())->registerRoutes();
});

add_action('init', static function (): void {
    (new ReactiphShortcode(new WordPressBridge()))->register();
});
