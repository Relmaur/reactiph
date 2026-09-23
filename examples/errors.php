<?php

declare(strict_types=1);

/**
 * Manual smoke test for the Reactiph exception foundation (see
 * docs/adr/0009-*.md). Run with:
 *   php examples/errors.php
 *
 * Demonstrates catching a template parse failure through the common
 * ReactiphException interface and reading its structured diagnostics —
 * the same shape every framework exception carries, human-readable via
 * getMessage()/hint(), machine-readable via code()/context()/json_encode().
 */

require __DIR__ . '/../vendor/autoload.php';

use Reactiph\Exception\ReactiphException;
use Reactiph\Template\Parser;

$brokenTemplate = '<div><span>Missing the closing tags';

try {
    (new Parser())->parse($brokenTemplate);
} catch (ReactiphException $e) {
    echo "Human-readable:\n";
    echo '  ' . $e->getMessage() . "\n";
    echo '  Hint: ' . ($e->hint() ?? '(none)') . "\n\n";

    echo "Machine-readable:\n";
    echo '  ' . json_encode($e, JSON_PRETTY_PRINT) . "\n";
}
