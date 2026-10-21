<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Http;

use Emoti\CommonResources\Enums\Lang;
use Emoti\CommonResources\Enums\ServiceName;
use Emoti\CommonResources\Facades\ErrorReporter;
use Emoti\CommonResources\Facades\MetricsReporter;
use Emoti\CommonResources\Services\Monitoring\ErrorReporterInterface;
use Emoti\CommonResources\Services\Monitoring\MetricsReporterInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

final class ServiceHttpClient
{
    public const METRIC_REQUESTS = 'http.client.requests';
    public const METRIC_DURATION = 'http.client.duration';

    public const STATUS_CONNECTION_ERROR = 'connection_error';

    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_RETRY = 'retry';
    public const OUTCOME_FAILURE = 'failure';

    private ?Lang $locale = null;

    /** @var list<int> */
    private array $expectedStatuses = [];

    /** @var array<string, scalar|null> */
    private array $context = [];

    /**
     * @param int $retries Extra attempts after a connection error or a 5xx.
     */
    public function __construct(
        private readonly ServiceName $service,
        private readonly string $baseUrl,
        private readonly string $tokenHeader,
        private readonly ?string $token,
        private readonly float $timeout = 5,
        private readonly int $retries = 0,
        private readonly int $retryDelayMs = 100,
        private readonly bool $verifyTls = true,
    ) {}

    public function withLocale(Lang $locale): self
    {
        $client = clone $this;
        $client->locale = $locale;

        return $client;
    }

    // These statuses come back as a response, without an error report.
    public function expecting(int ...$statuses): self
    {
        $client = clone $this;
        $client->expectedStatuses = array_values(array_unique([...$this->expectedStatuses, ...$statuses]));

        return $client;
    }

    /**
     * @param array<string, scalar|null> $context Extra data for the breadcrumbs and the Sentry event.
     */
    public function withContext(array $context): self
    {
        $client = clone $this;
        $client->context = [...$this->context, ...$context];

        return $client;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @throws ServiceHttpException
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ServiceHttpException
     */
    public function post(string $path, array $data = []): Response
    {
        return $this->send('POST', $path, ['json' => $data]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ServiceHttpException
     */
    public function put(string $path, array $data = []): Response
    {
        return $this->send('PUT', $path, ['json' => $data]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ServiceHttpException
     */
    public function send(string $method, string $path, array $options = []): Response
    {
        $context = ['method' => $method, 'url' => $this->url($path), ...$this->context];

        if ($this->token === null || $this->token === '') {
            throw $this->fail(new ServiceHttpException("The {$this->service->value} key is not configured.", $this->service), $context);
        }

        for ($attempt = 1; ; $attempt++) {
            $context['attempt'] = $attempt;
            $canRetry = $attempt <= $this->retries;
            $startedAt = hrtime(true);

            try {
                $response = $this->request()->send($method, (string) $context['url'], $options);
            } catch (ConnectionException $exception) {
                $this->measure($method, self::STATUS_CONNECTION_ERROR, $canRetry ? self::OUTCOME_RETRY : self::OUTCOME_FAILURE, $startedAt);

                if ($canRetry) {
                    $this->retryAfter([...$context, 'error' => $exception->getMessage()]);

                    continue;
                }

                throw $this->fail(new ServiceHttpException("{$this->service->value} is unreachable.", $this->service, previous: $exception), $context);
            }

            $context['status_code'] = $response->status();

            if ($canRetry && $response->serverError()) {
                $this->measure($method, (string) $response->status(), self::OUTCOME_RETRY, $startedAt);
                $this->retryAfter($context);

                continue;
            }

            $error = $this->error($response);
            $this->measure($method, (string) $response->status(), $error === null ? self::OUTCOME_SUCCESS : self::OUTCOME_FAILURE, $startedAt);

            if ($error !== null) {
                throw $this->fail($error, $context);
            }

            $this->breadcrumb($context, ErrorReporterInterface::LEVEL_INFO);

            return $response;
        }
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->connectTimeout($this->timeout)
            ->withoutRedirecting()
            ->acceptJson()
            ->withHeaders([$this->tokenHeader => (string) $this->token])
            ->when($this->locale !== null, fn(PendingRequest $request): PendingRequest => $request->withHeaders(['Accept-Language' => $this->locale?->value]))
            ->when(!$this->verifyTls, fn(PendingRequest $request): PendingRequest => $request->withoutVerifying());
    }

    private function error(Response $response): ?ServiceHttpException
    {
        if (in_array($response->status(), $this->expectedStatuses, true)) {
            return null;
        }

        if ($response->redirect()) {
            return new ServiceHttpException("{$this->service->value} responded with a redirect.", $this->service, $response->status());
        }

        if (!$response->successful()) {
            return new ServiceHttpException($this->errorMessage($response), $this->service, $response->status());
        }

        if (!is_array($response->json())) {
            return new ServiceHttpException("{$this->service->value} responded without JSON.", $this->service, $response->status());
        }

        return null;
    }

    private function url(string $path): string
    {
        return $path === ''
            ? rtrim($this->baseUrl, '/')
            : rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function errorMessage(Response $response): string
    {
        $message = is_array($response->json()) ? $response->json('message') : null;

        return is_string($message) && $message !== ''
            ? $message
            : "{$this->service->value} responded with HTTP {$response->status()}.";
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function retryAfter(array $context): void
    {
        $this->breadcrumb($context, ErrorReporterInterface::LEVEL_WARNING);

        Sleep::for($this->retryDelayMs)->milliseconds();
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function fail(ServiceHttpException $exception, array $context): ServiceHttpException
    {
        $this->breadcrumb([...$context, 'error' => $exception->getMessage()], ErrorReporterInterface::LEVEL_ERROR);

        ErrorReporter::captureException($exception, $context, ['service' => $this->service->value]);

        return $exception;
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function breadcrumb(array $context, string $level): void
    {
        ErrorReporter::addBreadcrumb(
            "{$context['method']} {$context['url']}",
            'http',
            $context,
            $level,
            ErrorReporterInterface::TYPE_HTTP,
        );
    }

    private function measure(string $method, string $status, string $outcome, int $startedAt): void
    {
        $attributes = ['service' => $this->service->value, 'method' => $method, 'status' => $status, 'outcome' => $outcome];

        try {
            MetricsReporter::count(self::METRIC_REQUESTS, 1, $attributes);
            MetricsReporter::distribution(
                self::METRIC_DURATION,
                (hrtime(true) - $startedAt) / 1_000_000,
                $attributes,
                MetricsReporterInterface::UNIT_MILLISECOND,
            );
        } catch (Throwable) {
            // A broken metrics transport must not fail the call.
        }
    }
}
