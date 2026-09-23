<?php

declare(strict_types=1);

// taw/core's own files each guard with `if (!defined('ABSPATH')) { exit; }`
// -- WordPress's standard "block direct file access" convention. A real WP
// bootstrap defines this before anything else loads; this stub
// environment has to as well, or merely autoloading a taw/core class
// (MetaBlock, BaseBlock, ...) terminates the process before any of this
// package's own code ever runs.
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Minimal, hand-rolled WordPress function stubs so this package's tests
 * can run without a real WordPress install — same approach and same
 * reasoning as `reactiph/wordpress-bridge`'s own `tests/bootstrap.php`
 * (a light footprint over a heavy WP test framework dependency), just
 * covering the smaller surface `ReactiveMetaBlock` and the real
 * `taw/core` classes it extends (`MetaBlock`, `BaseBlock`) actually touch
 * during construction and `render()` — not `taw/core`'s own full WP
 * surface, which is `taw/core`'s own test suite's concern, not this
 * package's.
 *
 * Every stub records what it was called with into
 * `$GLOBALS['reactiph_wp_stub_calls'][$function][]`, so a test can assert
 * against real call data. Also scanned by `phpstan.neon` (`scanFiles`) as
 * the signature source for this package's own static analysis.
 */

$GLOBALS['reactiph_wp_stub_calls'] = [];

function reactiph_wp_stub_reset(): void
{
    $GLOBALS['reactiph_wp_stub_calls'] = [];
}

/**
 * @param array<int, mixed> $args
 */
function reactiph_wp_stub_record(string $function, array $args): void
{
    $GLOBALS['reactiph_wp_stub_calls'][$function][] = $args;
}

/**
 * @return array<int, array<int, mixed>>
 */
function reactiph_wp_stub_calls(string $function): array
{
    return $GLOBALS['reactiph_wp_stub_calls'][$function] ?? [];
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        reactiph_wp_stub_record('add_action', [$hook, $callback, $priority, $acceptedArgs]);

        return true;
    }
}

if (!function_exists('get_template_directory')) {
    function get_template_directory(): string
    {
        reactiph_wp_stub_record('get_template_directory', []);

        return '/var/www/wp-content/themes/test-theme';
    }
}

if (!function_exists('get_template_directory_uri')) {
    function get_template_directory_uri(): string
    {
        reactiph_wp_stub_record('get_template_directory_uri', []);

        return 'https://example.test/wp-content/themes/test-theme';
    }
}

if (!function_exists('get_the_ID')) {
    function get_the_ID(): int|false
    {
        reactiph_wp_stub_record('get_the_ID', []);

        return false;
    }
}

if (!function_exists('wp_enqueue_script')) {
    /**
     * @param string[] $deps
     */
    function wp_enqueue_script(
        string $handle,
        string $src = '',
        array $deps = [],
        string|bool|null $ver = false,
        bool $inFooter = false,
    ): void {
        reactiph_wp_stub_record('wp_enqueue_script', [$handle, $src, $deps, $ver, $inFooter]);
    }
}

if (!function_exists('wp_localize_script')) {
    /**
     * @param array<string, mixed> $data
     */
    function wp_localize_script(string $handle, string $objectName, array $data): bool
    {
        reactiph_wp_stub_record('wp_localize_script', [$handle, $objectName, $data]);

        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(int|string $action = -1): string
    {
        reactiph_wp_stub_record('wp_create_nonce', [$action]);

        return 'stub-nonce-' . $action;
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        reactiph_wp_stub_record('rest_url', [$path]);

        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }
}
