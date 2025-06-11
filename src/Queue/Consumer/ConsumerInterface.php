<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Consumer;

use Closure;
use Exception;

interface ConsumerInterface
{
    /**
     * @param Closure(Exception): void $captureException
     */
    public function consume(Closure $captureException): void;
}