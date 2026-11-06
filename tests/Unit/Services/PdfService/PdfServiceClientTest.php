<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PdfService;

use Emoti\CommonResources\Services\BinaryFile;
use Emoti\CommonResources\Services\PdfService\PdfServiceClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class PdfServiceClientTest extends TestCase
{
    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    public function test_transform_html_to_pdf_sends_api_key_header(): void
    {
        $client = new PdfServiceClient('https://pdf.test/', 'secret-key', $this->mockHttpClient());

        $client->transformHtmlToPdf('<p>x</p>');

        $this->assertSame('secret-key', $this->lastRequest()->getHeaderLine('X-API-Key'));
    }

    public function test_merge_pdfs_sends_api_key_header(): void
    {
        $client = new PdfServiceClient('https://pdf.test/', 'secret-key', $this->mockHttpClient());

        $client->mergePdfs([BinaryFile::fromBinary('%PDF-1')]);

        $this->assertSame('secret-key', $this->lastRequest()->getHeaderLine('X-API-Key'));
    }

    public function test_api_key_header_is_omitted_when_key_is_null(): void
    {
        $client = new PdfServiceClient('https://pdf.test/', null, $this->mockHttpClient());

        $client->mergePdfs([BinaryFile::fromBinary('%PDF-1')]);

        $this->assertFalse($this->lastRequest()->hasHeader('X-API-Key'));
    }

    public function test_api_key_header_is_omitted_when_key_is_empty(): void
    {
        $client = new PdfServiceClient('https://pdf.test/', '', $this->mockHttpClient());

        $client->transformHtmlToPdf('<p>x</p>');

        $this->assertFalse($this->lastRequest()->hasHeader('X-API-Key'));
    }

    public function test_api_key_is_trimmed(): void
    {
        $client = new PdfServiceClient('https://pdf.test/', " secret-key\n", $this->mockHttpClient());

        $client->mergePdfs([BinaryFile::fromBinary('%PDF-1')]);

        $this->assertSame('secret-key', $this->lastRequest()->getHeaderLine('X-API-Key'));
    }

    public function test_api_key_header_is_omitted_when_key_is_whitespace(): void
    {
        $client = new PdfServiceClient('https://pdf.test/', '   ', $this->mockHttpClient());

        $client->mergePdfs([BinaryFile::fromBinary('%PDF-1')]);

        $this->assertFalse($this->lastRequest()->hasHeader('X-API-Key'));
    }

    private function mockHttpClient(): Client
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], base64_encode('%PDF-1'))]));
        $stack->push(Middleware::history($this->history));

        return new Client(['base_uri' => 'https://pdf.test/', 'handler' => $stack]);
    }

    private function lastRequest(): RequestInterface
    {
        $this->assertNotEmpty($this->history);

        return $this->history[array_key_last($this->history)]['request'];
    }
}
