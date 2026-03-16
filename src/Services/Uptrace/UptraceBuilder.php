<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\Uptrace;

use InvalidArgumentException;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\Sampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanLimitsBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use Throwable;

class UptraceBuilder
{
    private static bool $registered = false;

    private OtlpHttpTransportFactory $transportFactory;
    private string $dsn = '';
    private ?SamplerInterface $sampler = null;

    /** @var array<string, scalar|array|null> */
    private array $resourceAttrs = [];
    private string $serviceName = '';
    private string $serviceVersion = '';

    public function __construct()
    {
        $this->transportFactory = new OtlpHttpTransportFactory();
        $this->sampler = new Sampler\ParentBased(new Sampler\AlwaysOnSampler());
    }

    public function setDsn(string $dsn): self
    {
        $this->dsn = $dsn;
        return $this;
    }

    public function setServiceName(string $serviceName): self
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    public function setServiceVersion(string $serviceVersion): self
    {
        $this->serviceVersion = $serviceVersion;
        return $this;
    }

    public function setResourceAttributes(array $resourceAttrs): self
    {
        $this->resourceAttrs = $resourceAttrs;
        return $this;
    }

    public function setSampler(SamplerInterface $sampler): self
    {
        $this->sampler = $sampler;
        return $this;
    }

    public function buildAndRegisterGlobal(): void
    {
        if (self::$registered) {
            return;
        }

        if (empty($this->dsn)) {
            $msg = 'Uptrace DSN is empty (provide UPTRACE_DSN env var)';
            throw new InvalidArgumentException($msg);
        }

        if (empty($this->serviceName)) {
            throw new InvalidArgumentException('Uptrace service name is empty');
        }

        try {
            $dsn = new Dsn($this->dsn);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid Uptrace DSN: %s', $e->getMessage()),
                previous: $e,
            );
        }

        $resource = $this->createResource();
        $meterProvider = $this->createMeterProvider($dsn, $resource);
        $tracerProvider = $this->createTracerProvider($dsn, $resource);
        $loggerProvider = $this->createLoggerProvider($dsn, $resource);

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        self::$registered = true;
    }

    private function createResource(): ResourceInfo
    {
        return ResourceInfo::create(Attributes::create($this->buildResourceAttributes()));
    }

    /** @return array<string, scalar|array|null> */
    private function buildResourceAttributes(): array
    {
        if (empty($this->serviceName)) {
            throw new InvalidArgumentException('Uptrace service name is empty');
        }

        return [
            ...$this->resourceAttrs,
            ServiceAttributes::SERVICE_NAME => $this->serviceName,
            ServiceAttributes::SERVICE_VERSION => $this->serviceVersion ?? '1.0.0',
        ];
    }

    private function createMeterProvider(Dsn $dsn, ResourceInfo $resource): MeterProvider
    {
        $reader = new ExportingReader(
            new MetricExporter(
                $this->createTransport($dsn, 'metrics'),
            ),
        );

        /** @var MeterProvider $meterProvider */
        $meterProvider = MeterProvider::builder()
            ->setResource($resource)
            ->addReader($reader)
            ->build();

        return $meterProvider;
    }

    private function createTracerProvider(
        Dsn $dsn,
        ResourceInfo $resource,
    ): TracerProvider {
        $exporter = new SpanExporter($this->createTransport($dsn, 'traces'));

        $processor = new BatchSpanProcessor(
            $exporter,
            Clock::getDefault(),
            BatchSpanProcessor::DEFAULT_MAX_QUEUE_SIZE,
            BatchSpanProcessor::DEFAULT_SCHEDULE_DELAY,
            BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT,
            BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
            true,
        );

        $spanLimits = (new SpanLimitsBuilder())->build();
        $idGenerator = new RandomIdGenerator();

        return new TracerProvider(
            [$processor],
            $this->sampler,
            $resource,
            $spanLimits,
            $idGenerator,
        );
    }

    private function createLoggerProvider(Dsn $dsn, ResourceInfo $resource): LoggerProvider
    {
        $exporter = new LogsExporter(
            $this->createTransport($dsn, 'logs'),
        );

        $processor = new BatchLogRecordProcessor(
            $exporter,
            Clock::getDefault(),
        );

        /** @var LoggerProvider $loggerProvider */
        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor($processor)
            ->build();

        return $loggerProvider;
    }

    private function createTransport(Dsn $dsn, string $transportPath): TransportInterface
    {
        return $this->transportFactory->create(
            sprintf('%s/v1/%s', $dsn->otlpHttpEndpoint, $transportPath),
            'application/json',
            ['uptrace-dsn' => $dsn->dsn],
            TransportFactoryInterface::COMPRESSION_GZIP,
        );
    }
}
