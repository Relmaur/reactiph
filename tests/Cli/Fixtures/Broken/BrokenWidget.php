<?php

declare(strict_types=1);

namespace Reactiph\Tests\Cli\Fixtures\Broken;

use Reactiph\Component\BaseComponent;

final class BrokenWidget extends BaseComponent
{
    public function template(): string
    {
        return '<div>unclosed';
    }
}
