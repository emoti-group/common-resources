<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\PdfService;

use InvalidArgumentException;

final class PdfServiceFactory
{
    public const DRIVER_LEGACY = 'legacy';

    public const DRIVER_CLOUDFLARE = 'cloudflare';

    public static function createPdfService(string $baseUri): PdfServiceInterface
    {
        if ($baseUri === '') {
            throw new InvalidArgumentException('legacy.base_uri is required when driver is legacy.');
        }

        return new PdfServiceClient($baseUri);
    }

    /**
     * @param array<string, mixed> $defaultPdfOptions
     */
    public static function createCloudflare(
        string $accountId,
        string $token,
        array $defaultPdfOptions = [
            'format' => 'a4',
            'printBackground' => true,
        ],
        string $apiBase = 'https://api.cloudflare.com/client/v4',
    ): PdfServiceInterface
    {
        if ($accountId === '' || $token === '') {
            throw new InvalidArgumentException('cloudflare.account_id and cloudflare.api_token are required when driver is cloudflare.');
        }

        return new CloudflareBrowserRenderingPdfClient(
            $accountId,
            $token,
            $defaultPdfOptions,
            null,
            $apiBase,
        );
    }
}
