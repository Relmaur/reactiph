<?php

declare(strict_types=1);

/**
 * Manual smoke test for the "folder-based component" sugar layer (ADR
 * 0020): a component whose class and template live together in one
 * directory, discovered automatically instead of registered by hand.
 *
 * ReactiphExamples\Greeting (examples/folder-based/Greeting.php) never
 * overrides template() -- BaseComponent's default implementation loads
 * examples/folder-based/Greeting.reactiph.html instead. Nothing here
 * calls ComponentRegistry::register('Greeting', ...) directly;
 * ComponentDiscovery::registerDirectory() finds and registers it by its
 * own short class name, which is what lets <Greeting /> below resolve.
 *
 * Run with:
 *   php examples/folder-components.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Reactiph\Component\BaseComponent;
use Reactiph\Component\ComponentDiscovery;

ComponentDiscovery::registerDirectory(__DIR__ . '/folder-based');

final class Page extends BaseComponent
{
    public string $visitor = 'Reactiph';

    public function template(): string
    {
        return '<main><Greeting name="{$visitor}" /></main>';
    }
}

$page = new Page();

echo $page->render() . "\n";
