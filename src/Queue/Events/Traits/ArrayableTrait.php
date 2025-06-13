<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Traits;

use BackedEnum;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionException;

trait ArrayableTrait
{
    /**
     * Returns an array of all object constructor properties.
     */
    public function data(): array
    {
        $reflection = new ReflectionClass($this);
        $data = [];

        foreach ($reflection->getConstructor()->getParameters() as $param) {
            $name = $param->getName();
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);
                $data[$name] = $property->getValue($this);
            }
        }

        return $data;
    }

    /**
     * Creates a new instance of the class from an array of data.
     * @throws ReflectionException
     */
    public static function fromArray(array $messageContent): static
    {
        $reflection = new ReflectionClass(static::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            $value = $messageContent[$name] ?? $messageContent['data'][$name] ?? null;
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
            'data' => $this->data(),
            'resourceId' => $this->resourceId(),
            'resourceUuid' => $this->resourceUuid()?->toString(),
            'version' => $this->version(),
            'eventId' => $this->eventId()->toString(),
            'routingKey' => $this->routingKey(),
        ];
    }
}
