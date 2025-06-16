<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Queue\Events\Traits\ArrayableTrait;
use Emoti\CommonResources\Queue\Events\Traits\DispatchableTrait;
use Emoti\CommonResources\Queue\Events\Traits\ExtraPropertiesTrait;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
abstract class AbstractEmotiEvent
{
    use ExtraPropertiesTrait;
    use DispatchableTrait;
    use ArrayableTrait;
}