<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\Monitoring;

use Throwable;

/**
 * Project-owned abstraction over the error-monitoring vendor (currently Sentry).
 *
 * Call sites depend on this interface only — no `\Sentry\*` type may appear at a
 * call site. Swapping the vendor means providing a new implementation and
 * rebinding it; nothing else changes.
 */
interface ErrorReporterInterface
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_FATAL = 'fatal';

    /**
     * Semantic breadcrumb types — this abstraction's own vocabulary, NOT a
     * specific vendor's. Each implementation maps these to its provider's
     * taxonomy and falls back to TYPE_DEFAULT for anything it does not support.
     * Keep this set small and stable; add a case only when it is meaningful
     * across monitoring vendors.
     */
    public const TYPE_DEFAULT = 'default';
    public const TYPE_NAVIGATION = 'navigation';
    public const TYPE_HTTP = 'http';
    public const TYPE_USER = 'user';
    public const TYPE_ERROR = 'error';

    /**
     * @param array<string, mixed> $extras
     * @param array<string, string> $tags
     */
    public function captureException(Throwable $exception, array $extras = [], array $tags = []): void;

    public function captureMessage(string $message, string $level = self::LEVEL_INFO): void;

    /**
     * @param array<string, mixed> $metadata
     */
    public function addBreadcrumb(
        string $message,
        string $category = 'default',
        array $metadata = [],
        string $level = self::LEVEL_INFO,
        string $type = self::TYPE_DEFAULT,
    ): void;

    /**
     * Set (or, with null, clear) the user context attached to captured events.
     *
     * @param array<string, mixed>|null $user
     */
    public function setUserContext(?array $user): void;

    public function clearBreadcrumbs(): void;

    public function captureUnhandledException(Throwable $exception): void;
}
