<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures;

use Reactiph\Component\BaseComponent;

final class GreetingComponent extends BaseComponent
{
    public string $name = 'World';

    public function template(): string
    {
        return '<div class="greeting"><h1>Hello, {$name}!</h1></div>';
    }
}
