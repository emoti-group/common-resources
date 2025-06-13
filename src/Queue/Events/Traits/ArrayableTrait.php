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
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            $value = $data[$name] ?? $data['data'][$name] ?? null;
            $type = $property->getType();

            if ($value !== null && $type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if (enum_exists($typeName)) {
                    $value = $typeName::from($value);
                } elseif (class_exists($typeName) && method_exists($typeName, 'fromArray')) {
                    $value = $typeName::fromArray($value);
                } elseif (class_exists($typeName) && method_exists($typeName, 'from')) {
                    $value = $typeName::from($value);
                }
            }

            if ($value !== null) {
                $property->setValue($instance, $value);
            }
        }

        return $instance;
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
