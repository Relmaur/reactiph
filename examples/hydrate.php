<?php

declare(strict_types=1);

/**
 * Manual smoke test for Part 5 (click bindings + real transpiled method
 * execution + DOM patching, ADR 0016/0017), building on Part 3's hydration
 * payload wiring. Generates a static HTML page -- server-rendered markup
 * with comment-marked {$expr} spots, an embedded hydration manifest, and
 * the Counter component's own increment() method and {$count} expression
 * transpiled to real JS by ComponentTranspiler -- that a real browser can
 * load to prove clicking the button runs the ACTUAL transpiled PHP method
 * (not hand-written JS) and that the resulting state change is patched
 * into the visible DOM, not just observable via console/debug-attribute.
 * Run with:
 *   php examples/hydrate.php
 * then open examples/hydrate-output.html in a browser.
 */

require __DIR__ . '/../vendor/autoload.php';

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

$counter = new Counter();
$counter->count = 3;
$counter->hydrationId = 'counter-1';

$ssrHtml = $counter->render();
$hydrationScript = HydrationSerializer::toScriptTag([HydrationSerializer::payloadFor($counter)]);
$componentJs = (new ComponentTranspiler())->transpileComponent(Counter::class);

$page = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reactiph hydration demo (Part 5 slice 1)</title>
</head>
<body>
    <p>Server-rendered count is 3. Click Increment: the REAL transpiled
    <code>increment()</code> method runs client-side and the visible count
    below is patched to match (open the devtools console to see it log the
    new state, and inspect the <code>data-reactiph-debug-state</code>
    attribute on the div below).</p>
    {$ssrHtml}
    {$hydrationScript}
    <script src="../packages/runtime-js/php-runtime.js"></script>
    <script>{$componentJs}</script>
    <script src="../packages/runtime-js/hydrate.js"></script>
</body>
</html>

HTML;

$outputPath = __DIR__ . '/hydrate-output.html';
file_put_contents($outputPath, $page);

echo "Wrote {$outputPath}\n";
echo "SSR output: {$ssrHtml}\n";
echo "Transpiled component JS:\n{$componentJs}\n";
