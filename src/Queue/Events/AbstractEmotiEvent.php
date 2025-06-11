<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

abstract class AbstractEmotiEvent
{
    use BasicTrait;
    use DispatchableTrait;
}