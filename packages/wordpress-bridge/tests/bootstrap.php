<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Minimal, hand-rolled WordPress function/class stubs so this package's
 * tests can run without a real WP install and without a heavy WP test
 * framework dependency (no wp-phpunit, no Brain Monkey) — matching the
 * project's general preference for a light footprint (see e.g.
 * `NodeRunner` in the core test suite, which hand-rolls Node execution
 * rather than pulling in a JS testing framework).
 *
 * Every stub records what it was called with into
 * `$GLOBALS['reactiph_wp_stub_calls'][$function][]`, so a test can assert
 * against real call data (arguments actually passed) instead of the stub
 * silently swallowing them. `reactiph_wp_stub_reset()` clears both the
 * call log and nonce-validity flag between tests.
 *
 * Also scanned by `phpstan.neon` (`scanFiles`) so static analysis of
 * `src/` resolves these symbols too — real signatures, not throwaway ones,
 * so a signature drift from real WP would show up as a type error here
 * before it ever reached a live site.
 */

$GLOBALS['reactiph_wp_stub_calls'] = [];
$GLOBALS['reactiph_wp_stub_nonce_valid'] = true;

function reactiph_wp_stub_reset(): void
{
    $GLOBALS['reactiph_wp_stub_calls'] = [];
    $GLOBALS['reactiph_wp_stub_nonce_valid'] = true;
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

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        reactiph_wp_stub_record('rest_url', [$path]);

        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('register_rest_route')) {
    /**
     * @param array<string, mixed> $args
     */
    function register_rest_route(string $namespace, string $route, array $args): bool
    {
        reactiph_wp_stub_record('register_rest_route', [$namespace, $route, $args]);

        return true;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, int|string $action = -1): int|false
    {
        reactiph_wp_stub_record('wp_verify_nonce', [$nonce, $action]);

        return $GLOBALS['reactiph_wp_stub_nonce_valid'] ? 1 : false;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(int|string $action = -1): string
    {
        reactiph_wp_stub_record('wp_create_nonce', [$action]);

        return 'stub-nonce-' . $action;
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

if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script(string $handle, string $data, string $position = 'after'): bool
    {
        reactiph_wp_stub_record('wp_add_inline_script', [$handle, $data, $position]);

        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        reactiph_wp_stub_record('add_filter', [$hook, $callback, $priority, $acceptedArgs]);

        return true;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void
    {
        reactiph_wp_stub_record('add_shortcode', [$tag, $callback]);
    }
}

if (!function_exists('shortcode_atts')) {
    /**
     * @param array<string, string> $pairs
     * @param array<string, string>|string $atts
     * @return array<string, string>
     */
    function shortcode_atts(array $pairs, array|string $atts, string $shortcode = ''): array
    {
        $atts = is_array($atts) ? $atts : [];
        $out = [];

        foreach ($pairs as $name => $default) {
            $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
        }

        return $out;
    }
}

if (!function_exists('wp_unique_id')) {
    function wp_unique_id(string $prefix = ''): string
    {
        static $count = 0;

        return $prefix . (string) (++$count);
    }
}

if (!class_exists('WP_Error')) {
    final class WP_Error
    {
        /**
         * @param array<string, mixed> $data
         */
        public function __construct(
            public readonly string $code = '',
            public readonly string $message = '',
            public readonly array $data = [],
        ) {
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    final class WP_REST_Response
    {
        /** @var array<string, string> */
        private array $headers = [];

        public function __construct(private readonly mixed $data = null, private readonly int $status = 200)
        {
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }

        /**
         * @return array<string, string>
         */
        public function get_headers(): array
        {
            return $this->headers;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    final class WP_REST_Request
    {
        /**
         * @param array<string, mixed> $params
         * @param array<string, string> $headers
         */
        public function __construct(
            private readonly array $params = [],
            private readonly array $headers = [],
            private readonly string $body = '',
        ) {
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function get_header(string $key): ?string
        {
            return $this->headers[$key] ?? null;
        }

        public function get_body(): string
        {
            return $this->body;
        }
    }
}
