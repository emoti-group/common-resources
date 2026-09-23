<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Consumer;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;

/**
 * Per-message state the consumer collects for the queue job metrics while a message is
 * being processed, so the final attempt's outcome can be recorded once, after ack()/nack().
 */
#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
final class MessageMetrics
{
    /** FQCN of what ran (listener), or of the event when nothing ran, or `unknown`. */
    public string $job = 'unknown';

    /** Number of the attempt currently running / last run (1-based, 0 = nothing ran). */
    public int $attempt = 0;

    /** Handler execution time of the last attempt only; earlier attempts were recorded as retries. */
    public int $lastAttemptNanoseconds = 0;

    /** Acked without running a listener (no binding for the event). */
    public bool $skipped = false;

    public function lastAttemptMs(): float
    {
        return $this->lastAttemptNanoseconds / 1_000_000;
    }
}
