<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures\Discoverable;

/**
 * Does not extend BaseComponent at all -- ComponentDiscovery must skip it.
 */
final class NotAComponent
{
}
