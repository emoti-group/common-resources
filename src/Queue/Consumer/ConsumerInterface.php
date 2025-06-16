<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Consumer;

use Closure;
use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Exception;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
interface ConsumerInterface
{
    /**
     * @param Closure(Exception): void $captureException
     */
    public function consume(Closure $captureException): void;
}