<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures;

use Reactiph\Component\BaseComponent;

final class ThumbnailComponent extends BaseComponent
{
    public string $title = '';
    public string $image = '';
    public int $likes = 0;

    public function template(): string
    {
        return '<div class="thumbnail"><img src="{$image}"><h3>{$title}</h3><LikeButton count="{$likes}" /></div>';
    }
}
