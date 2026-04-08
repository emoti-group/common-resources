<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\PdfService;

/**
 * Typed options for HTML -> PDF transformation.
 */
final class PdfTransformOptions
{
    /**
     * @param array<string, mixed> $extraPdfOptions
     */
    public function __construct(
        public readonly ?string $format = null,
        public readonly ?bool $landscape = null,
        public readonly ?int $marginTop = null,
        public readonly ?int $marginBottom = null,
        public readonly ?int $marginLeft = null,
        public readonly ?int $marginRight = null,
        public readonly ?bool $printBackground = null,
        public readonly array $extraPdfOptions = [],
    ) {}

    /**
     * @param array<string, mixed> $legacyOptions
     */
    public static function fromLegacyOptions(array $legacyOptions): self
    {
        return new self(
            self::toNullableString($legacyOptions['optionsFormat'] ?? null),
            self::toNullableBool($legacyOptions['optionsLandscape'] ?? null),
            self::toNullableInt($legacyOptions['optionsMarginTop'] ?? null),
            self::toNullableInt($legacyOptions['optionsMarginBottom'] ?? null),
            self::toNullableInt($legacyOptions['optionsMarginLeft'] ?? null),
            self::toNullableInt($legacyOptions['optionsMarginRight'] ?? null),
            self::toNullableBool($legacyOptions['optionsPrintBackground'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $pdfOptions
     */
    public static function fromCloudflarePdfOptions(array $pdfOptions): self
    {
        $margin = is_array($pdfOptions['margin'] ?? null) ? $pdfOptions['margin'] : [];
        unset($pdfOptions['margin']);

        $known = [
            'format' => $pdfOptions['format'] ?? null,
            'landscape' => $pdfOptions['landscape'] ?? null,
            'printBackground' => $pdfOptions['printBackground'] ?? null,
        ];
        unset($pdfOptions['format'], $pdfOptions['landscape'], $pdfOptions['printBackground']);

        return new self(
            self::toNullableString($known['format']),
            self::toNullableBool($known['landscape']),
            self::toNullableInt($margin['top'] ?? null),
            self::toNullableInt($margin['bottom'] ?? null),
            self::toNullableInt($margin['left'] ?? null),
            self::toNullableInt($margin['right'] ?? null),
            self::toNullableBool($known['printBackground']),
            $pdfOptions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toCloudflarePdfOptions(): array
    {
        $options = $this->extraPdfOptions;

        if ($this->format !== null) {
            $options['format'] = $this->format;
        }
        if ($this->landscape !== null) {
            $options['landscape'] = $this->landscape;
        }
        if ($this->printBackground !== null) {
            $options['printBackground'] = $this->printBackground;
        }

        $margin = [];
        if ($this->marginTop !== null) {
            $margin['top'] = $this->marginTop;
        }
        if ($this->marginBottom !== null) {
            $margin['bottom'] = $this->marginBottom;
        }
        if ($this->marginLeft !== null) {
            $margin['left'] = $this->marginLeft;
        }
        if ($this->marginRight !== null) {
            $margin['right'] = $this->marginRight;
        }
        if ($margin !== []) {
            $options['margin'] = $margin;
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $options = [];

        if ($this->format !== null) {
            $options['optionsFormat'] = $this->format;
        }

        if ($this->landscape !== null) {
            $options['optionsLandscape'] = $this->landscape;
        }
        if ($this->marginTop !== null) {
            $options['optionsMarginTop'] = $this->marginTop;
        }
        if ($this->marginBottom !== null) {
            $options['optionsMarginBottom'] = $this->marginBottom;
        }
        if ($this->marginLeft !== null) {
            $options['optionsMarginLeft'] = $this->marginLeft;
        }
        if ($this->marginRight !== null) {
            $options['optionsMarginRight'] = $this->marginRight;
        }
        if ($this->printBackground !== null) {
            $options['optionsPrintBackground'] = $this->printBackground;
        }

        return $options;
    }

    private static function toNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function toNullableBool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private static function toNullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
