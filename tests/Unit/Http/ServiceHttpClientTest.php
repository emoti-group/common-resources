<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Emoti\CommonResources\Enums\Lang;
use Emoti\CommonResources\Enums\ServiceName;
use Emoti\CommonResources\Http\ServiceHttpClient;
use Emoti\CommonResources\Http\ServiceHttpException;
use Emoti\CommonResources\Services\Monitoring\ErrorReporterInterface;
use Emoti\CommonResources\Services\Monitoring\MetricsReporterInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Unit\Services\Monitoring\FakeMetricsReporter;

final class ServiceHttpClientTest extends TestCase
{
    private FakeMetricsReporter $metrics;

    private ErrorReporterInterface&MockInterface $errors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metrics = new FakeMetricsReporter();
        $this->app->instance(MetricsReporterInterface::class, $this->metrics);
        $this->errors = Mockery::spy(ErrorReporterInterface::class);
        $this->app->instance(ErrorReporterInterface::class, $this->errors);
        Http::preventStrayRequests();
        Sleep::fake();
    }

    public function test_sends_the_key_the_locale_and_json_headers(): void
    {
        Http::fake(['https://api.test/v1/items*' => Http::response(['data' => ['id' => 1]])]);

        $response = $this->client()->withLocale(Lang::EE)->get('/items', ['page' => 2]);

        $this->assertSame(1, $response->json('data.id'));
        Http::assertSent(fn(Request $request): bool => $request->url() === 'https://api.test/v1/items?page=2'
            && $request->method() === 'GET'
            && $request->header('X-TEST-TOKEN') === ['secret']
            && $request->header('Accept-Language') === ['ee']
            && $request->header('Accept') === ['application/json']);
    }

    public function test_sends_the_body_as_json_without_a_locale(): void
    {
        Http::fake(['https://api.test/v1/items' => Http::response(['data' => null])]);

        $this->client()->put('items', ['name' => 'x']);

        Http::assertSent(fn(Request $request): bool => $request->method() === 'PUT'
            && $request->data() === ['name' => 'x']
            && $request->header('Accept-Language') === []);
    }

    public function test_the_with_methods_return_a_copy(): void
    {
        Http::fake(['*' => Http::response(['data' => null])]);
        $client = $this->client();

        $client->withLocale(Lang::PL)->expecting(404)->withContext(['site' => 'pl']);
        $client->get('items');

        Http::assertSent(fn(Request $request): bool => $request->header('Accept-Language') === []);
        $this->errors->shouldHaveReceived('addBreadcrumb')
            ->with(Mockery::any(), 'http', Mockery::on(fn(array $context): bool => !isset($context['site'])), Mockery::any(), Mockery::any());
    }

    public function test_uses_the_timeout_and_does_not_follow_redirects(): void
    {
        $options = [];
        Http::fake(function (Request $request, array $requestOptions) use (&$options) {
            $options = $requestOptions;

            return Http::response(['data' => null]);
        });

        $this->client(timeout: 2)->get('items');

        $this->assertSame(2.0, $options['timeout']);
        $this->assertSame(2.0, $options['connect_timeout']);
        $this->assertFalse($options['allow_redirects']);
    }

    public function test_records_the_count_and_the_duration_by_status_and_outcome(): void
    {
        Http::fake(['*' => Http::response(['data' => null], 201)]);

        $this->client()->post('items');

        $attributes = ['service' => 'gifts-api', 'method' => 'POST', 'status' => '201', 'outcome' => ServiceHttpClient::OUTCOME_SUCCESS];
        $this->assertSame([['name' => ServiceHttpClient::METRIC_REQUESTS, 'value' => 1, 'attributes' => $attributes]], $this->metrics->counts);
        $this->assertCount(1, $this->metrics->distributions);
        $this->assertSame(ServiceHttpClient::METRIC_DURATION, $this->metrics->distributions[0]['name']);
        $this->assertSame($attributes, $this->metrics->distributions[0]['attributes']);
        $this->assertSame(MetricsReporterInterface::UNIT_MILLISECOND, $this->metrics->distributions[0]['unit']);
    }

    public function test_a_broken_metrics_transport_does_not_fail_the_call(): void
    {
        Http::fake(['*' => Http::response(['data' => null])]);
        $this->metrics->throws = true;

        $this->assertTrue($this->client()->get('items')->ok());
    }

    public function test_adds_a_breadcrumb_for_a_successful_call(): void
    {
        Http::fake(['*' => Http::response(['data' => null])]);

        $this->client()->get('items');

        $this->errors->shouldHaveReceived('addBreadcrumb')
            ->once()
            ->with('GET https://api.test/v1/items', 'http', Mockery::subset(['status_code' => 200, 'attempt' => 1]), ErrorReporterInterface::LEVEL_INFO, ErrorReporterInterface::TYPE_HTTP);
        $this->errors->shouldNotHaveReceived('captureException');
    }

    public function test_an_error_status_throws_reports_and_keeps_the_message(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Elasticsearch is down'], 500)]);

        $exception = $this->catch(fn() => $this->client()->withContext(['site' => 'pl'])->get('items'));

        $this->assertSame('Elasticsearch is down', $exception->getMessage());
        $this->assertSame(500, $exception->status);
        $this->assertSame(ServiceName::GiftsApi, $exception->service);
        $this->errors->shouldHaveReceived('addBreadcrumb')
            ->once()
            ->with(Mockery::any(), 'http', Mockery::subset(['status_code' => 500, 'site' => 'pl']), ErrorReporterInterface::LEVEL_ERROR, ErrorReporterInterface::TYPE_HTTP);
        $this->errors->shouldHaveReceived('captureException')
            ->once()
            ->with($exception, Mockery::subset(['site' => 'pl', 'status_code' => 500, 'method' => 'GET']), ['service' => 'gifts-api']);
        $this->assertSame(ServiceHttpClient::OUTCOME_FAILURE, $this->metrics->counts[0]['attributes']['outcome']);
    }

    public function test_a_client_error_is_reported_too(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalid gifts-cms API token'], 403)]);

        $exception = $this->catch(fn() => $this->client()->get('items'));

        $this->assertSame(403, $exception->status);
        $this->errors->shouldHaveReceived('captureException')->once();
    }

    public function test_an_error_status_without_a_message_names_the_status(): void
    {
        Http::fake(['*' => Http::response('Bad gateway', 502)]);

        $this->assertSame('gifts-api responded with HTTP 502.', $this->catch(fn() => $this->client()->get('items'))->getMessage());
    }

    public function test_a_connection_error_throws_and_is_measured(): void
    {
        Http::fake(fn() => throw new ConnectionException('timed out'));

        $exception = $this->catch(fn() => $this->client()->get('items'));

        $this->assertSame('gifts-api is unreachable.', $exception->getMessage());
        $this->assertNull($exception->status);
        $this->assertInstanceOf(ConnectionException::class, $exception->getPrevious());
        $this->assertSame(ServiceHttpClient::STATUS_CONNECTION_ERROR, $this->metrics->counts[0]['attributes']['status']);
        $this->errors->shouldHaveReceived('captureException')->once();
    }

    public function test_a_redirect_is_an_error(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.test'])]);

        $exception = $this->catch(fn() => $this->client()->get('items'));

        $this->assertSame('gifts-api responded with a redirect.', $exception->getMessage());
        $this->assertSame(302, $exception->status);
        Http::assertSentCount(1);
    }

    public function test_a_body_that_is_not_json_is_an_error(): void
    {
        Http::fake(['*' => Http::response('<html>login</html>', 200)]);

        $exception = $this->catch(fn() => $this->client()->get('items'));

        $this->assertSame('gifts-api responded without JSON.', $exception->getMessage());
        $this->errors->shouldHaveReceived('captureException')->once();
    }

    public function test_an_expected_status_returns_the_response_without_a_report(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Not found'], 404)]);

        $response = $this->client()->expecting(404)->get('items');

        $this->assertTrue($response->notFound());
        $this->errors->shouldNotHaveReceived('captureException');
        $this->assertSame(ServiceHttpClient::OUTCOME_SUCCESS, $this->metrics->counts[0]['attributes']['outcome']);
    }

    public function test_a_missing_key_throws_without_a_request(): void
    {
        Http::fake();

        $exception = $this->catch(fn() => $this->client(token: '')->get('items'));

        $this->assertSame('The gifts-api key is not configured.', $exception->getMessage());
        Http::assertNothingSent();
        $this->errors->shouldHaveReceived('captureException')->once();
    }

    public function test_retries_a_server_error_and_measures_each_attempt(): void
    {
        Http::fakeSequence()->push(['message' => 'down'], 503)->push(['data' => null]);

        $this->client(retries: 1)->get('items');

        Http::assertSentCount(2);
        Sleep::assertSlept(fn($duration): bool => (int) $duration->totalMilliseconds === 50);
        $this->assertSame(
            [['503', ServiceHttpClient::OUTCOME_RETRY], ['200', ServiceHttpClient::OUTCOME_SUCCESS]],
            array_map(fn(array $count): array => [$count['attributes']['status'], $count['attributes']['outcome']], $this->metrics->counts),
        );
        $this->errors->shouldHaveReceived('addBreadcrumb')
            ->with(Mockery::any(), 'http', Mockery::subset(['status_code' => 503, 'attempt' => 1]), ErrorReporterInterface::LEVEL_WARNING, ErrorReporterInterface::TYPE_HTTP);
        $this->errors->shouldNotHaveReceived('captureException');
    }

    public function test_retries_a_connection_error(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return $attempts === 1
                ? throw new ConnectionException('timed out')
                : Http::response(['data' => null]);
        });

        $this->client(retries: 1)->get('items');

        $this->assertSame(2, $attempts);
        $this->assertSame(ServiceHttpClient::OUTCOME_RETRY, $this->metrics->counts[0]['attributes']['outcome']);
    }

    public function test_reports_once_when_the_retry_fails_too(): void
    {
        Http::fake(['*' => Http::response(['message' => 'down'], 503)]);

        $exception = $this->catch(fn() => $this->client(retries: 1)->get('items'));

        $this->assertSame(503, $exception->status);
        Http::assertSentCount(2);
        $this->errors->shouldHaveReceived('captureException')
            ->once()
            ->with($exception, Mockery::subset(['attempt' => 2]), Mockery::any());
    }

    public function test_does_not_retry_a_client_error(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalid'], 422)]);

        $this->catch(fn() => $this->client(retries: 1)->get('items'));

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    private function client(string $token = 'secret', float $timeout = 5, int $retries = 0): ServiceHttpClient
    {
        return new ServiceHttpClient(ServiceName::GiftsApi, 'https://api.test/v1/', 'X-TEST-TOKEN', $token, $timeout, $retries, retryDelayMs: 50);
    }

    private function catch(callable $call): ServiceHttpException
    {
        try {
            $call();
        } catch (ServiceHttpException $exception) {
            return $exception;
        }

        $this->fail('No ServiceHttpException was thrown.');
    }
}
