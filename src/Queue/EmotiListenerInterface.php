<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue;

use Emoti\CommonResources\Queue\Events\EmotiEventInterface;

interface EmotiListenerInterface
{
    public function handle(EmotiEventInterface $event): void;
}