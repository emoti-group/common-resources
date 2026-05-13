<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use Emoti\CommonResources\Queue\Message;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function test_json_round_trip_preserves_content_and_class(): void
    {
        $message = new Message(
            content: ['foo' => 'bar', 'nested' => ['a' => 1]],
            class: 'Some\\Event\\Class',
        );

        $restored = Message::fromJson($message->toJson());

        $this->assertSame($message->content, $restored->content);
        $this->assertSame($message->class, $restored->class);
    }

    public function test_to_array_contains_content_and_class_keys(): void
    {
        $message = new Message(content: ['id' => 42], class: 'MyEvent');

        $array = $message->toArray();

        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('class', $array);
        $this->assertSame(['id' => 42], $array['content']);
        $this->assertSame('MyEvent', $array['class']);
    }
}
