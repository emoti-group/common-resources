<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\PdfService;

use Emoti\CommonResources\Services\BinaryFile;

interface PdfServiceInterface
{
    public function transformHtmlToPdf(string $html): BinaryFile;

    /**
     * @param non-empty-list<BinaryFile> $pdfs
     */
    public function mergePdfs(array $pdfs): BinaryFile;
}
