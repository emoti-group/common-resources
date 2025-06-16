<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

use Emoti\CommonResources\Queue\Events\Traits\ArrayableTrait;
use Emoti\CommonResources\Queue\Events\Traits\DefaultMethodsTrait;
use Emoti\CommonResources\Queue\Events\Traits\DispatchableTrait;

abstract class AbstractEmotiEvent
{
    use DefaultMethodsTrait;
    use DispatchableTrait;
    use ArrayableTrait;
}