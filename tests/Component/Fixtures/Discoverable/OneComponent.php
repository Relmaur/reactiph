<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures\Discoverable;

use Reactiph\Component\BaseComponent;

final class OneComponent extends BaseComponent
{
    public function template(): string
    {
        return '<p>one</p>';
    }
}
