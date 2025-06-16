<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Traits;

use BackedEnum;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;
use ReflectionType;

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
     */
    public static function fromArray(array $messageContent): static
    {
        $reflection = new ReflectionClass(static::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            $value = self::getPropertyValueFromArray($messageContent, $name);
            $type = $property->getType();
            $value = self::resolvePropertyValue($value, $type);
            self::setPropertyValue($property, $instance, $value);
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

    /**
     * @param array<string, mixed> $messageContent
     */
    private static function getPropertyValueFromArray(array $messageContent, string $name): mixed
    {
        return $messageContent[$name] ?? $messageContent['data'][$name] ?? null;
    }

    private static function resolvePropertyValue(mixed $value, ?ReflectionType $type): mixed
    {
        if ($value !== null && $type !== null && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if (enum_exists($typeName)) {
                /** @var BackedEnum $typeName */
                return $typeName::from($value);
            }

            if (class_exists($typeName) && method_exists($typeName, 'fromArray')) {
                return $typeName::fromArray($value);
            }

            if (class_exists($typeName) && method_exists($typeName, 'from')) {
                return $typeName::from($value);
            }

            if (str_contains($typeName, 'UuidInterface')) {
                return Uuid::fromString($value);
            }
        }

        return $value;
    }

    private static function setPropertyValue(ReflectionProperty $property, object $instance, mixed $value): void
    {
        if ($value !== null) {
            $property->setValue($instance, $value);
        }
    }
}
