<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\Monitoring;

use Sentry\Breadcrumb;
use Sentry\Laravel\Integration;
use Sentry\Severity;
use Sentry\State\Scope;
use Throwable;

use function Sentry\addBreadcrumb;
use function Sentry\captureException;
use function Sentry\captureMessage;
use function Sentry\configureScope;
use function Sentry\withScope;

/**
 * Sentry-backed implementation of {@see ErrorReporterInterface}.
 *
 * This is the only class in the Laravel projects that references `\Sentry\*`.
 */
final class SentryErrorReporter implements ErrorReporterInterface
{
    public function captureException(Throwable $exception, array $extras = [], array $tags = []): void
    {
        if ($extras === [] && $tags === []) {
            captureException($exception);

            return;
        }

        withScope(function (Scope $scope) use ($exception, $extras, $tags): void {
            foreach ($extras as $key => $value) {
                $scope->setExtra($key, $value);
            }

            foreach ($tags as $key => $value) {
                $scope->setTag($key, $value);
            }

            captureException($exception);
        });
    }

    public function captureMessage(string $message, string $level = self::LEVEL_INFO): void
    {
        captureMessage($message, $this->toSeverity($level));
    }

    public function addBreadcrumb(
        string $message,
        string $category = 'default',
        array $metadata = [],
        string $level = self::LEVEL_INFO,
        string $type = 'default',
    ): void {
        addBreadcrumb(new Breadcrumb(
            $this->toBreadcrumbLevel($level),
            $type,
            $category,
            $message,
            $metadata,
        ));
    }

    public function setUserContext(?array $user): void
    {
        configureScope(static function (Scope $scope) use ($user): void {
            $scope->setUser($user ?? []);
        });
    }

    public function clearBreadcrumbs(): void
    {
        configureScope(static fn (Scope $scope) => $scope->clearBreadcrumbs());
    }

    public function captureUnhandledException(Throwable $exception): void
    {
        Integration::captureUnhandledException($exception);
    }

    private function toSeverity(string $level): Severity
    {
        return match ($level) {
            self::LEVEL_DEBUG => Severity::debug(),
            self::LEVEL_WARNING => Severity::warning(),
            self::LEVEL_ERROR => Severity::error(),
            self::LEVEL_FATAL => Severity::fatal(),
            default => Severity::info(),
        };
    }

    private function toBreadcrumbLevel(string $level): string
    {
        return match ($level) {
            self::LEVEL_DEBUG => Breadcrumb::LEVEL_DEBUG,
            self::LEVEL_WARNING => Breadcrumb::LEVEL_WARNING,
            self::LEVEL_ERROR => Breadcrumb::LEVEL_ERROR,
            self::LEVEL_FATAL => Breadcrumb::LEVEL_FATAL,
            default => Breadcrumb::LEVEL_INFO,
        };
    }
}
