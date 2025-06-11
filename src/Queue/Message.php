<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue;

/**
 * @property class-string $handler
 */
final class Message
{
    public function __construct(
        public readonly array $content,
        public readonly string $handler,
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'handler' => $this->handler,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public static function fromJson(string $json): self
    {
        $body = json_decode($json, true);

        return new self($body['content'], $body['handler']);
    }
}