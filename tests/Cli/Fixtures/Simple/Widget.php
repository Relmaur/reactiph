<?php

declare(strict_types=1);

namespace Reactiph\Tests\Cli\Fixtures\Simple;

use Reactiph\Component\BaseComponent;

final class Widget extends BaseComponent
{
    public int $count = 0;

    public function template(): string
    {
        return '<div class="widget"><span>{$count}</span><button (click)="increment">+</button></div>';
    }

    public function increment(): void
    {
        $this->count = $this->count + 1;
    }
}
