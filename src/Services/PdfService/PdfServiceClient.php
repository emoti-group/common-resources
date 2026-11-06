<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\PdfService;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Emoti\CommonResources\Services\BinaryFile;

final class PdfServiceClient implements PdfServiceInterface
{
    private const API_KEY_HEADER = 'X-API-Key';

    private ClientInterface $httpClient;

    private ?string $apiKey;

    /**
     * @param string|null $apiKey Sent as X-API-Key; pdf-service checks it only when its API_KEY env is set.
     */
    public function __construct(
        string $pdfServiceUrl,
        ?string $apiKey = null,
        ?ClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client(['base_uri' => $pdfServiceUrl]);
        $this->apiKey = $apiKey !== null && trim($apiKey) !== '' ? trim($apiKey) : null;
    }

    /**
     * @throws Exception
     */
    public function transformHtmlToPdf(
        string $html,
        ?string $pageFormat = 'a5',
        array|PdfTransformOptions $options = [],
    ): BinaryFile
    {
        try {
            if ($options instanceof PdfTransformOptions) {
                $queryParams = [
                    ...$options->toArray(),
                    'optionsFormat' => $options->format ?? $pageFormat,
                    'waitForContent' => true,
                ];
            } else {
                $queryParams = [
                    ...$options,
                    'optionsFormat' => $pageFormat,
                    'waitForContent' => true,
                ];
            }
            $query = http_build_query($queryParams);

            $result = $this->httpClient->request(
                'POST',
                sprintf('/%s?%s', 'generate-pdf', $query),
                $this->requestOptions(['json' => ['body' => base64_encode($html)]]),
            );

            return BinaryFile::fromBase64($result->getBody()->getContents());
        } catch (GuzzleException $e) {
            throw new Exception('Failed to generate PDF file: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function mergePdfs(array $pdfs): BinaryFile
    {
        try {
            $data = [
                'pdfs' => array_map(
                    static fn(BinaryFile $pdf): string => $pdf->toBase64(),
                    $pdfs,
                ),
            ];

            $result = $this->httpClient->request(
                'POST',
                sprintf('/%s', 'merge-pdf'),
                $this->requestOptions(['json' => $data]),
            );

            return BinaryFile::fromBase64($result->getBody()->getContents());
        } catch (GuzzleException $e) {
            throw new Exception('Failed to merge PDF files: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function requestOptions(array $options): array
    {
        if ($this->apiKey === null) {
            return $options;
        }

        return [...$options, 'headers' => [self::API_KEY_HEADER => $this->apiKey]];
    }
}
