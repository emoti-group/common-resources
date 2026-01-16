<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services;

class BinaryFile
{
    private function __construct(
        private string $content,
        public ?string $name,
    ) {}

    public static function fromBinary(string $value, ?string $name = null): self
    {
        return new self($value, $name);
    }

    public static function fromBase64(string $value, ?string $name = null): self
    {
        return new self(base64_decode($value), $name);
    }

    public function toString(): string
    {
        return $this->content;
    }

    public function toBase64(): string
    {
        return base64_encode($this->content);
    }
}
