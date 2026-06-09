<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Facades;

use Emoti\CommonResources\Services\Monitoring\ErrorReporterInterface;
use Illuminate\Support\Facades\Facade;
use Throwable;

/**
 * @method static void captureException(Throwable $exception, array $extras = [], array $tags = [])
 * @method static void captureMessage(string $message, string $level = ErrorReporterInterface::LEVEL_INFO)
 * @method static void addBreadcrumb(string $message, string $category = 'default', array $metadata = [], string $level = ErrorReporterInterface::LEVEL_INFO, string $type = 'default')
 * @method static void setUserContext(?array $user)
 * @method static void clearBreadcrumbs()
 * @method static void captureUnhandledException(Throwable $exception)
 *
 * @see ErrorReporterInterface
 */
final class ErrorReporter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ErrorReporterInterface::class;
    }
}
