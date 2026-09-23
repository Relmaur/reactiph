<?php

declare(strict_types=1);

namespace Reactiph\Tests\Transpiler\Fixtures;

use Reactiph\Component\BaseComponent;

final class CounterComponentFixture extends BaseComponent
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
