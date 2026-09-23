<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures;

use Reactiph\Component\BaseComponent;

final class LikeButtonComponent extends BaseComponent
{
    public int $count = 0;

    public function template(): string
    {
        return '<button class="like">{$count} likes</button>';
    }
}
