<?php

declare(strict_types=1);

/**
 * Manual smoke test for Part 3 (hydration payload + client bootstrap).
 * Generates a static HTML page — server-rendered markup plus an embedded
 * hydration manifest — that a real browser can load to prove the
 * hand-written JS stub (packages/runtime-js/hydrate-stub.js) attaches to
 * the existing DOM and makes the Increment button work, without any
 * server round-trip or client-side re-render. Run with:
 *   php examples/hydrate.php
 * then open examples/hydrate-output.html in a browser.
 */

require __DIR__ . '/../vendor/autoload.php';

use Reactiph\Component\BaseComponent;
use Reactiph\Runtime\HydrationSerializer;

final class Counter extends BaseComponent
{
    public int $count = 0;

    public function template(): string
    {
        return <<<'HTML'
<div class="counter"><span class="count">{$count}</span><button type="button" class="increment">Increment</button></div>
HTML;
    }
}

$counter = new Counter();
$counter->count = 3;
$counter->hydrationId = 'counter-1';

$ssrHtml = $counter->render();
$hydrationScript = HydrationSerializer::toScriptTag([HydrationSerializer::payloadFor($counter)]);

$page = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reactiph hydration demo (Part 3)</title>
</head>
<body>
    <p>Server-rendered count is 3. Click Increment — it should update to 4, 5, ...
    entirely client-side, with no page reload or network request.</p>
    {$ssrHtml}
    {$hydrationScript}
    <script src="../packages/runtime-js/hydrate-stub.js"></script>
</body>
</html>

HTML;

$outputPath = __DIR__ . '/hydrate-output.html';
file_put_contents($outputPath, $page);

echo "Wrote {$outputPath}\n";
echo "SSR output: {$ssrHtml}\n";
