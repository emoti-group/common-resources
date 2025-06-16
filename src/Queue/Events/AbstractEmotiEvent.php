<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

use Emoti\CommonResources\Queue\Events\Traits\ArrayableTrait;
use Emoti\CommonResources\Queue\Events\Traits\DispatchableTrait;
use Emoti\CommonResources\Queue\Events\Traits\ExtraPropertiesTrait;

abstract class AbstractEmotiEvent
{
    use ExtraPropertiesTrait;
    use DispatchableTrait;
    use ArrayableTrait;
}