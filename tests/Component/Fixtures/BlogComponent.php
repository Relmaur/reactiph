<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures;

use Reactiph\Component\BaseComponent;

final class BlogComponent extends BaseComponent
{
    public function template(): string
    {
        return <<<'HTML'
<div class="blog"><h1>Latest posts</h1><Thumbnail title="Hello World" image="/img/hello.png" likes="{$this->helloLikes()}" /><Thumbnail title="Second Post" image="/img/second.png" likes="{$this->secondLikes()}" /></div>
HTML;
    }

    public function helloLikes(): int
    {
        return 4;
    }

    public function secondLikes(): int
    {
        return 9;
    }
}
