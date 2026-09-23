<?php

declare(strict_types=1);

/**
 * Manual smoke test for Part 1 (SSR-only templating). Run with:
 *   php examples/render.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Reactiph\Component\BaseComponent;

final class GreetingCard extends BaseComponent
{
    public string $name = 'World';
    public int $unreadCount = 0;

    public function template(): string
    {
        return <<<'HTML'
<div class="card">
    <h1>Hello, {$name}!</h1>
    <p>You have {$this->unreadLabel()} unread messages.</p>
</div>
HTML;
    }

    public function unreadLabel(): string
    {
        return (string) $this->unreadCount;
    }
}

$card = new GreetingCard();
$card->name = 'Reactiph';
$card->unreadCount = 3;

echo $card->render() . PHP_EOL;
