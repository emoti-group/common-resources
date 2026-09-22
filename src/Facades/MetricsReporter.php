<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Facades;

use Emoti\CommonResources\Services\Monitoring\MetricsReporterInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void count(string $name, int|float $value = 1, array<string, int|float|string|bool|null> $attributes = [])
 * @method static void distribution(string $name, int|float $value, array<string, int|float|string|bool|null> $attributes = [], string $unit = MetricsReporterInterface::UNIT_NONE)
 * @method static void gauge(string $name, int|float $value, array<string, int|float|string|bool|null> $attributes = [], string $unit = MetricsReporterInterface::UNIT_NONE)
 * @method static void flush()
 *
 * @see MetricsReporterInterface
 */
final class MetricsReporter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MetricsReporterInterface::class;
    }
}
