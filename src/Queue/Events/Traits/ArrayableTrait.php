<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Traits;

use BackedEnum;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

trait ArrayableTrait
{
    /**
     * Returns an array of all object properties.
     */
    public function data(): array
    {
        $reflection = new ReflectionClass($this);
        $data = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
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
                    /** @var BackedEnum $typeName */
                    $value = $typeName::from($value);
                } elseif (class_exists($typeName) && method_exists($typeName, 'fromArray')) {
                    $value = $typeName::fromArray($value);
                } elseif (class_exists($typeName) && method_exists($typeName, 'from')) {
                    $value = $typeName::from($value);
                } elseif (str_contains($typeName, 'UuidInterface')) {
                    $value = Uuid::fromString($value);
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
            'routingKey' => $this->routingKey(),
            'eventId' => $this->eventId()->toString(),
            'resourceId' => $this->resourceId(),
            'resourceUuid' => $this->resourceUuid()?->toString(),
            'data' => $this->data(),
            'version' => $this->version(),
        ];
    }
}
