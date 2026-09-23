<?php

/**
 * Plugin Name: Reactiph Bridge Demo
 * Description: Part 7 smoke-test plugin (ADR 0019) -- registers a Counter component via the [reactiph] shortcode, proving WordPressBridge/ReactiphShortcode work against a real WordPress install, not just unit tests against hand-rolled stubs. Also registers [reactiph_guestbook], a genuine RPC-round-trip demo (see Guestbook below).
 * Version: 0.1.0
 * License: MIT
 *
 * Not published anywhere -- a throwaway verification artifact for this
 * one repo. To use: `composer install` in this directory (resolves
 * reactiph/wordpress-bridge and reactiph/reactiph via the path
 * repositories in composer.json, symlinked from this repo -- no
 * publish/tag step needed), then place or symlink this whole directory
 * into a WordPress install's wp-content/plugins/ and activate it. Add
 * `[reactiph component="ReactiphDemo\\Counter"]` to any post/page for the
 * client-transpiled demo, or `[reactiph_guestbook]` for the RPC demo.
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

/**
 * A genuine RPC-round-trip demo, mirroring examples/bridge-server.php's
 * own Guestbook exactly (Part 6) -- one action, sign(), that does real
 * work outside the transpiler's allow-listed subset (here, a WordPress
 * options-table read/write, the idiomatic WP persistence mechanism;
 * bridge-server.php used raw file I/O for the same reason against
 * DefaultBridge). ReactiphShortcode always calls ComponentTranspiler on
 * whatever it renders (ADR 0018's still-open "no server-only marking"
 * thread), so Guestbook is deliberately never routed through it --
 * render_guestbook_shortcode() below renders it SSR-only and wires its
 * button by hand, the same "not new template syntax" choice ADR 0018
 * made for bridge-server.php's Guestbook.
 */
final class Guestbook extends BaseComponent
{
    private const OPTION_NAME = 'reactiph_guestbook_count';

    public int $signatureCount = 0;

    public function template(): string
    {
        return <<<'HTML'
<div class="guestbook"><p>Signatures so far: <span class="signature-count">{$signatureCount}</span></p><button type="button" id="reactiph-guestbook-sign">Sign (real server round-trip)</button></div>
HTML;
    }

    public function sign(): void
    {
        $count = (int) get_option(self::OPTION_NAME, 0);
        ++$count;
        update_option(self::OPTION_NAME, $count);
        $this->signatureCount = $count;
    }
}

add_action('rest_api_init', static function (): void {
    (new WordPressBridge())->registerRoutes();
});

add_action('init', static function (): void {
    (new ReactiphShortcode(new WordPressBridge()))->register();
    add_shortcode('reactiph_guestbook', __NAMESPACE__ . '\\render_guestbook_shortcode');
});

function render_guestbook_shortcode(): string
{
    $guestbook = new Guestbook();
    $guestbook->signatureCount = (int) get_option('reactiph_guestbook_count', 0);

    $rpcUrl = (new WordPressBridge())->rpcEndpointUrl();
    $nonce = wp_create_nonce('wp_rest');

    ob_start();
    ?>
    <?= $guestbook->render() ?>
    <script>
    document.getElementById('reactiph-guestbook-sign').addEventListener('click', function () {
        fetch(<?= wp_json_encode($rpcUrl) ?>, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': <?= wp_json_encode($nonce) ?>,
            },
            body: JSON.stringify({
                component: 'ReactiphDemo\\Guestbook',
                method: 'sign',
                state: {},
                args: [],
            }),
        })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                document.querySelector('.signature-count').textContent = result.state.signatureCount;
            });
    });
    </script>
    <?php
    return (string) ob_get_clean();
}
