<?php

declare(strict_types=1);

/**
 * Manual smoke test for Part 2 (component tree: custom tags, props,
 * nesting, slots). Run with:
 *   php examples/blog.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Reactiph\Component\BaseComponent;
use Reactiph\Component\ComponentRegistry;

final class LikeButton extends BaseComponent
{
    public int $count = 0;

    public function template(): string
    {
        return '<button class="like">{$count} likes</button>';
    }
}

final class Thumbnail extends BaseComponent
{
    public string $title = '';
    public string $image = '';
    public int $likes = 0;

    public function template(): string
    {
        return <<<'HTML'
<div class="thumbnail"><img src="{$image}"><h3>{$title}</h3><LikeButton count="{$likes}" /></div>
HTML;
    }
}

final class Notice extends BaseComponent
{
    public string $heading = '';

    public function template(): string
    {
        return '<aside class="notice"><h4>{$heading}</h4><div class="notice-body">{$slot}</div></aside>';
    }
}

final class Blog extends BaseComponent
{
    public function template(): string
    {
        return <<<'HTML'
<div class="blog">
    <Notice heading="Welcome">Thanks for reading, {$this->readerName()}!</Notice>
    <h1>Latest posts</h1>
    <Thumbnail title="Hello World" image="/img/hello.png" likes="4" />
    <Thumbnail title="Second Post" image="/img/second.png" likes="9" />
</div>
HTML;
    }

    public function readerName(): string
    {
        return 'Reactiph';
    }
}

ComponentRegistry::register('LikeButton', LikeButton::class);
ComponentRegistry::register('Thumbnail', Thumbnail::class);
ComponentRegistry::register('Notice', Notice::class);

echo (new Blog())->render() . PHP_EOL;
