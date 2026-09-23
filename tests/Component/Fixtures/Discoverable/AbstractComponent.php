<?php

declare(strict_types=1);

namespace Reactiph\Tests\Component\Fixtures\Discoverable;

use Reactiph\Component\BaseComponent;

/**
 * A real BaseComponent subclass, but abstract -- ComponentDiscovery must
 * skip it (nothing to instantiate).
 */
abstract class AbstractComponent extends BaseComponent
{
}
