<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures;

use Reactiph\Component\BaseComponent;

final class CardComponent extends BaseComponent
{
    public string $heading = '';

    public function template(): string
    {
        return '<div class="card"><h2>{$heading}</h2><div class="card-body">{$slot}</div></div>';
    }
}
