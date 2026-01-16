<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\PdfService;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Emoti\CommonResources\Services\BinaryFile;

final class PdfServiceClient implements PdfServiceInterface
{
    private Client $httpClient;

    public function __construct(string $pdfServiceUrl)
    {
        $this->httpClient = new Client(['base_uri' => $pdfServiceUrl]);
    }

    /**
     * @throws Exception
     */
    public function transformHtmlToPdf(string $html, ?string $pageFormat = 'a5', array $options = []): BinaryFile
    {
        try {
            $queryParams = [
                ...$options,
                'optionsFormat' => $pageFormat,
                'waitForContent' => true,
            ];
            $query = http_build_query($queryParams);

            $result = $this->httpClient->post(
                sprintf('/%s?%s', 'generate-pdf', $query),
                ['json' => ['body' => base64_encode($html)]],
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

            $result = $this->httpClient->post(
                sprintf('/%s', 'merge-pdf'),
                ['json' => $data],
            );

            return BinaryFile::fromBase64($result->getBody()->getContents());
        } catch (GuzzleException $e) {
            throw new Exception('Failed to merge PDF files: ' . $e->getMessage());
        }
    }
}
