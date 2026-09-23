<?php

declare(strict_types=1);

/**
 * Part 6 end-to-end example: DefaultBridge serving a real request
 * lifecycle through PHP's built-in development server (the actual target
 * ADR 0004/0018 describe for a plain PHP app, not a static file:// page).
 * Run from the repo root with:
 *   php -S localhost:8080 examples/bridge-server.php
 * then open http://localhost:8080/ in a browser.
 *
 * Demonstrates two independent things through one running server:
 *  - Counter (from Part 5): SSR + hydration manifest + comment-marker DOM
 *    patching, unchanged, except the runtime JS now loads via real Bridge
 *    asset URLs (HTTP responses DefaultBridge serves) instead of a
 *    relative <script src> path — proving the asset-serving side of the
 *    Bridge actually works, not just that the file exists on disk.
 *  - Guestbook (new): a component with NO client-transpiled methods —
 *    its one action, sign(), does real file I/O, which is outside the
 *    transpiler's allow-listed subset (ADR 0011) and would fail to
 *    transpile if ComponentTranspiler were ever pointed at it. Its
 *    button is wired by hand-written page JS (deliberately not new
 *    template syntax — ADR 0018 decided against inventing an RPC-binding
 *    convention this part) that POSTs to the Bridge's RPC endpoint and
 *    displays the real server response.
 */

require __DIR__ . '/../vendor/autoload.php';

use Reactiph\Bridge\DefaultBridge;
use Reactiph\Bridge\RpcException;
use Reactiph\Component\BaseComponent;
use Reactiph\Runtime\HydrationSerializer;
use Reactiph\Transpiler\ComponentTranspiler;

final class Counter extends BaseComponent
{
    public int $count = 0;

    public function template(): string
    {
        return <<<'HTML'
<div class="counter"><span class="count">{$count}</span><button type="button" (click)="increment">Increment</button></div>
HTML;
    }

    public function increment(): void
    {
        $this->count = $this->count + 1;
    }
}

final class Guestbook extends BaseComponent
{
    public int $signatureCount = 0;

    public function template(): string
    {
        return <<<'HTML'
<div class="guestbook"><p>Signatures so far: <span class="signature-count">{$signatureCount}</span></p><button type="button" id="sign-button">Sign (real server round-trip)</button></div>
HTML;
    }

    /**
     * Deliberately does something PhpToJs's allow-listed subset can't
     * express (real file I/O) — proof this genuinely needs the server,
     * not a demo of the same arithmetic running over HTTP instead of
     * in-browser.
     */
    public function sign(): void
    {
        $path = sys_get_temp_dir() . '/reactiph-guestbook-count.txt';
        $count = is_file($path) ? (int) file_get_contents($path) : 0;
        ++$count;
        file_put_contents($path, (string) $count);
        $this->signatureCount = $count;
    }
}

$bridge = new DefaultBridge();
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$assetsPrefix = $bridge->assetUrl('');

if (str_starts_with($requestPath, $assetsPrefix)) {
    $name = substr($requestPath, strlen($assetsPrefix));
    $contents = $bridge->serveAsset($name);

    header('Content-Type: application/javascript');

    if ($contents === null) {
        http_response_code(404);
        echo "// Reactiph: unknown asset \"{$name}\"\n";
    } else {
        echo $contents;
    }

    return;
}

if ($requestPath === $bridge->rpcEndpointUrl()) {
    header('Content-Type: application/json');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'The RPC endpoint only accepts POST.']);

        return;
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);

    try {
        echo json_encode($bridge->handleRpc(is_array($payload) ? $payload : []));
    } catch (RpcException $e) {
        http_response_code(400);
        echo json_encode($e->jsonSerialize());
    }

    return;
}

// Everything else: render the page.
$counter = new Counter();
$counter->count = 3;
$counter->hydrationId = 'counter-1';

// Guestbook is SSR-only -- never hydrated, never transpiled; its one
// action is wired by hand below, entirely independent of the client
// hydration/patching pipeline Counter uses.
$guestbook = new Guestbook();

$ssrCounter = $counter->render();
$ssrGuestbook = $guestbook->render();
$hydrationScript = HydrationSerializer::toScriptTag([HydrationSerializer::payloadFor($counter)]);
$componentJs = (new ComponentTranspiler())->transpileComponent(Counter::class);

$phpRuntimeUrl = $bridge->assetUrl('php-runtime.js');
$hydrateUrl = $bridge->assetUrl('hydrate.js');
$rpcUrl = $bridge->rpcEndpointUrl();

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reactiph Bridge demo (Part 6)</title>
</head>
<body>
    <h1>Counter (client-transpiled, from Part 5)</h1>
    <p>Runtime JS now loads from real HTTP responses DefaultBridge serves
    (<code><?= htmlspecialchars($phpRuntimeUrl) ?></code>,
    <code><?= htmlspecialchars($hydrateUrl) ?></code>) instead of a
    relative file path.</p>
    <?= $ssrCounter ?>

    <?= $hydrationScript ?>
    <script src="<?= htmlspecialchars($phpRuntimeUrl) ?>"></script>
    <script><?= $componentJs ?></script>
    <script src="<?= htmlspecialchars($hydrateUrl) ?>"></script>

    <h1>Guestbook (server-bound RPC, new in Part 6)</h1>
    <p><code>sign()</code> does real file I/O -- outside the transpiler's
    allow-listed subset, so it could never run client-side. This button is
    wired by hand-written page JS calling the Bridge's RPC endpoint
    directly, not the template mini-language's event-binding syntax.</p>
    <?= $ssrGuestbook ?>
    <script>
    document.getElementById('sign-button').addEventListener('click', function () {
        fetch('<?= htmlspecialchars($rpcUrl) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                component: 'Guestbook',
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
</body>
</html>
