<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

abstract class AbstractEmotiEvent
{
    use DefaultMethodsTrait;
    use DispatchableTrait;
    use ArrayableTrait;
}