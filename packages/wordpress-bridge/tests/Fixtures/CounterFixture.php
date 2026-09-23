<?php

declare(strict_types=1);

namespace Reactiph\WordPressBridge\Tests\Fixtures;

use Reactiph\Component\BaseComponent;

final class CounterFixture extends BaseComponent
{
    public int $count = 0;

    public function template(): string
    {
        return '<div><span>{$count}</span><button (click)="increment">+</button></div>';
    }

    public function increment(): void
    {
        $this->count = $this->count + 1;
    }
}
