<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;

/**
 * @property class-string $class
 */
#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
final class Message
{
    public function __construct(
        public readonly array $content,
        public readonly string $class,
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'class' => $this->class,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public static function fromJson(string $json): self
    {
        $body = json_decode($json, true);

        return new self($body['content'], $body['class']);
    }
}