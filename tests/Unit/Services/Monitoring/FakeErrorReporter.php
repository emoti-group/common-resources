<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use Emoti\CommonResources\Services\Monitoring\ErrorReporterInterface;
use Throwable;

/**
 * Stand-in implementation used to prove a project can swap the error reporter
 * via the common-resources.error_reporter config key.
 */
final class FakeErrorReporter implements ErrorReporterInterface
{
    public function captureException(Throwable $exception, array $extras = [], array $tags = []): void {}

    public function captureMessage(string $message, string $level = self::LEVEL_INFO): void {}

    public function addBreadcrumb(
        string $message,
        string $category = 'default',
        array $metadata = [],
        string $level = self::LEVEL_INFO,
        string $type = 'default',
    ): void {}

    public function setUserContext(?array $user): void {}

    public function clearBreadcrumbs(): void {}

    public function captureUnhandledException(Throwable $exception): void {}
}
