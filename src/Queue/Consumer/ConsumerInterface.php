<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Consumer;

use Closure;
use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Throwable;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
interface ConsumerInterface
{
    /**
     * @param Closure(Throwable): void $captureException
     */
    public function consume(Closure $captureException, string $queueName): void;
}