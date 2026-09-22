<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use Emoti\CommonResources\Services\Monitoring\MetricsReporterInterface;
use RuntimeException;

/**
 * Records every call so tests can assert on emitted metrics; can be told to throw to
 * prove that a broken reporter never reaches the job.
 */
final class FakeMetricsReporter implements MetricsReporterInterface
{
    /** @var list<array{name: string, value: int|float, attributes: array<string, mixed>}> */
    public array $counts = [];

    /** @var list<array{name: string, value: int|float, attributes: array<string, mixed>, unit: string}> */
    public array $distributions = [];

    /** @var list<array{name: string, value: int|float, attributes: array<string, mixed>, unit: string}> */
    public array $gauges = [];

    public int $flushes = 0;

    public bool $throws = false;

    public function count(string $name, int|float $value = 1, array $attributes = []): void
    {
        $this->failIfRequested();
        $this->counts[] = ['name' => $name, 'value' => $value, 'attributes' => $attributes];
    }

    public function distribution(
        string $name,
        int|float $value,
        array $attributes = [],
        string $unit = self::UNIT_NONE,
    ): void {
        $this->failIfRequested();
        $this->distributions[] = ['name' => $name, 'value' => $value, 'attributes' => $attributes, 'unit' => $unit];
    }

    public function gauge(
        string $name,
        int|float $value,
        array $attributes = [],
        string $unit = self::UNIT_NONE,
    ): void {
        $this->failIfRequested();
        $this->gauges[] = ['name' => $name, 'value' => $value, 'attributes' => $attributes, 'unit' => $unit];
    }

    public function flush(): void
    {
        $this->failIfRequested();
        $this->flushes++;
    }

    private function failIfRequested(): void
    {
        if ($this->throws) {
            throw new RuntimeException('metrics transport is down');
        }
    }
}
