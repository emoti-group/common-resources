<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\PdfService;

use Emoti\CommonResources\Services\BinaryFile;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * HTML → PDF via Cloudflare Browser Rendering API.
 *
 * @see https://developers.cloudflare.com/browser-rendering/rest-api/pdf-endpoint/
 */
final class CloudflareBrowserRenderingPdfClient implements PdfServiceInterface
{
    private const DEFAULT_API_BASE = 'https://api.cloudflare.com/client/v4';
    /* @see https://developers.cloudflare.com/browser-run/limits/#workers-paid */
    private const TIMEOUT_SECONDS = 60;
    private const MAX_RETRIES = 3;

    private Client $httpClient;

    /**
     * @param array<string, mixed> $defaultPdfOptions
     */
    public function __construct(
        private string $accountId,
        private string $apiToken,
        private array $defaultPdfOptions = [
            'format' => 'a4',
            'printBackground' => true,
        ],
        ?Client $httpClient = null,
        private string $apiBaseUri = self::DEFAULT_API_BASE,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => self::TIMEOUT_SECONDS,
            'handler' => $this->createHandlerStack(),
        ]);
    }

    private function createHandlerStack(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::retry(
            function (int $retries, Request $request, ?Response $response = null): bool {
                if ($retries >= self::MAX_RETRIES) {
                    return false;
                }

                return $response !== null && $response->getStatusCode() === 429;
            },
            function (int $retries, Response $response): int {
                $retryAfter = $response->getHeaderLine('Retry-After');

                if ($retryAfter !== '' && is_numeric($retryAfter)) {
                    return (int) ((float) $retryAfter * 1000);
                }

                return (int) (1000 * 2 ** $retries);
            },
        ));

        return $stack;
    }

    /**
     * @param array<string, mixed>|PdfTransformOptions $pdfOptions
     *
     * @throws Exception
     */
    public function transformHtmlToPdf(string $html, array|PdfTransformOptions $pdfOptions = []): BinaryFile
    {
        $endpoint = rtrim($this->apiBaseUri, '/') . '/accounts/' . rawurlencode($this->accountId) . '/browser-rendering/pdf';

        $normalizedPdfOptions = $this->normalizePdfOptions($pdfOptions);
        $body = [
            'html' => $html,
            'pdfOptions' => array_replace($this->defaultPdfOptions, $normalizedPdfOptions),
        ];

        try {
            $response = $this->httpClient->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/pdf, application/json',
                ],
                'json' => $body,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new Exception('Failed to generate PDF via Cloudflare Browser Rendering: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $raw = $response->getBody()->getContents();
        $contentType = strtolower($response->getHeaderLine('Content-Type'));

        if ($status === 200 && $raw !== '' && (str_starts_with($raw, '%PDF') || str_contains($contentType, 'application/pdf'))) {
            return BinaryFile::fromBinary($raw);
        }

        if ($status >= 400 || str_contains($contentType, 'application/json') || str_starts_with(ltrim($raw), '{')) {
            $this->throwIfCloudflareApiError($raw, $status);
            throw new Exception(sprintf('Unexpected PDF API response (HTTP %d): %s', $status, $this->truncate($raw)));
        }

        if ($raw === '') {
            throw new Exception('Cloudflare PDF API returned an empty body.');
        }

        return BinaryFile::fromBinary($raw);
    }

    /**
     * @inheritDoc
     */
    public function mergePdfs(array $pdfs): BinaryFile
    {
        throw new Exception(
            'mergePdfs is not implemented for Cloudflare Browser Rendering. Use the legacy PdfServiceClient for merging, or merge PDFs in application code.',
        );
    }

    private function throwIfCloudflareApiError(string $raw, int $status): void
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new Exception(sprintf('PDF API error (HTTP %d): %s', $status, $this->truncate($raw)));
        }

        if (($decoded['success'] ?? null) === true) {
            return;
        }

        $errors = $decoded['errors'] ?? [];
        if ($errors === []) {
            throw new Exception(sprintf('PDF API error (HTTP %d): %s', $status, $this->truncate($raw)));
        }

        $messages = [];
        foreach ($errors as $err) {
            if (is_array($err) && isset($err['message'])) {
                $messages[] = (string) $err['message'];
            }
        }

        throw new Exception(
            'Cloudflare PDF API error (HTTP ' . $status . '): ' . ($messages !== [] ? implode('; ', $messages) : $this->truncate($raw)),
        );
    }

    private function truncate(string $s, int $max = 500): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, $max) . '…';
    }

    /**
     * @param array<string, mixed>|PdfTransformOptions $pdfOptions
     *
     * @return array<string, mixed>
     */
    private function normalizePdfOptions(array|PdfTransformOptions $pdfOptions): array
    {
        if ($pdfOptions instanceof PdfTransformOptions) {
            return $pdfOptions->toCloudflarePdfOptions();
        }

        if ($this->looksLikeLegacyOptions($pdfOptions)) {
            return PdfTransformOptions::fromLegacyOptions($pdfOptions)->toCloudflarePdfOptions();
        }

        return $pdfOptions;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function looksLikeLegacyOptions(array $options): bool
    {
        foreach (array_keys($options) as $key) {
            if (str_starts_with((string) $key, 'options')) {
                return true;
            }
        }

        return false;
    }
}
