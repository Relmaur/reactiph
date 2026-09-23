<?php

declare(strict_types=1);

namespace Reactiph\Tests\Bridge\Fixtures;

use Reactiph\Component\BaseComponent;

/**
 * Exercises the visibility exclusion in {@see \Reactiph\Component\OwnMethods}:
 * a private/protected helper declared directly on the component's own
 * class must never be RPC-callable or transpiled, even though it passes
 * the "declared on this class" check.
 */
final class ComponentWithPrivateMethodFixture extends BaseComponent
{
    public int $secret = 0;

    public function template(): string
    {
        return '<div>{$secret}</div>';
    }

    public function reveal(): void
    {
        $this->secret = $this->computeSecret();
    }

    private function computeSecret(): int
    {
        return 42;
    }
}
