<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\Uptrace;

use InvalidArgumentException;
use Log;

class Dsn
{
    public string $dsn = '';
    public string $siteUrl = '';
    public string $otlpHttpEndpoint = '';

    private string $scheme = '';
    private string $host = '';
    private int $httpPort = 14318;

    public function __construct($str)
    {
        $url = parse_url($str);
        if (!$url) {
            $msg = sprintf('Can\'t parse DSN \'%s\'', $str);
            throw new InvalidArgumentException($msg);
        }

        $this->scheme = $url['scheme'];
        $this->host = $url['host'];
        $this->httpPort = $url['port'] ?? 0;

        switch ($this->httpPort) {
            case 4317:
            case 14317:
                Log::warning(
                    sprintf(
                        'got port %d (OTLP/gRPC), but uptrace-php expects 14318 (OTLP/HTTP)',
                        $this->httpPort,
                    ),
                );
                break;
        }

        $this->dsn = $str;
        $this->siteUrl = $this->buildSiteUrl();
        $this->otlpHttpEndpoint = $this->buildOtlpHttpEndpoint();
    }

    private function buildSiteUrl(): string
    {
        if ($this->httpPort !== 0) {
            return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->httpPort);
        }
        return sprintf('%s://%s', $this->scheme, $this->host);
    }

    private function buildOtlpHttpEndpoint(): string
    {
        if ($this->httpPort !== 0) {
            return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->httpPort);
        }
        return sprintf('%s://%s', $this->scheme, $this->host);
    }
}
