<?php

declare(strict_types=1);

namespace Reactiph\Tests\Cli\Fixtures\Duplicate\B;

use Reactiph\Component\BaseComponent;

final class Widget extends BaseComponent
{
    public function template(): string
    {
        return '<p>b</p>';
    }
}
