<?php

declare(strict_types=1);

namespace Reactiph\Tests\Runtime\Fixtures;

use Reactiph\Component\BaseComponent;

final class CounterFixture extends BaseComponent
{
    public int $count = 0;
    public string $label = '';

    public function template(): string
    {
        return '<div><span>{$count}</span></div>';
    }
}
