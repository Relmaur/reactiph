<?php

declare(strict_types=1);

namespace Reactiph\TawBridge\Tests\Fixtures;

use Reactiph\TawBridge\ReactiveMetaBlock;

final class TestReactiveBlock extends ReactiveMetaBlock
{
    protected string $id = 'test-counter';

    protected function registerMetaboxes(): void
    {
        // Nothing to register for this fixture -- real blocks would
        // define Metabox fields here.
    }

    protected function getData(int|false $postId): array
    {
        return ['count' => 4];
    }

    protected function componentClass(): string
    {
        return CounterComponent::class;
    }
}
