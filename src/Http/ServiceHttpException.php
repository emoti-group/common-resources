<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Http;

use Emoti\CommonResources\Enums\ServiceName;
use RuntimeException;
use Throwable;

final class ServiceHttpException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ServiceName $service,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
