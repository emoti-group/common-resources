<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Traits;

use ReflectionClass;

trait ArrayableTrait
{
    /**
     * Returns an array of all object properties.
     */
    public function data(): array
    {
        $reflection = new ReflectionClass($this);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            $data[$property->getName()] = $property->getValue($this);
        }

        return $data;
    }

    /**
     * Creates a new instance of the class from an array of data.
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();
        $params = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $params[] = $data[$name] ?? null;
        }

        return $reflection->newInstanceArgs($params);
    }

    public function toArray(): array
    {
        return [
            'site' => $this->site()->value,
            'routing_key' => $this->routingKey(),
            'event_id' => $this->eventId()->toString(),
            'resource_id' => $this->resourceId(),
            'resource_uuid' => $this->resourceUuid()?->toString(),
            'data' => $this->data(),
            'version' => $this->version(),
        ];
    }
}
