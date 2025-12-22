<?php

namespace Emoti\CommonResources\DTO\PostHog;

use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Data;

final class PropertiesData extends Data
{
    public function __construct(
        public string $environment,
        public string $platform,
    ) {}

    public static function fromArrays(array ...$data): self
    {
        $classProperties = self::getClassProperties();
        $mergedData = array_merge(...$data);
        $dtoData = [];
        $additionalData = [];

        foreach ($mergedData as $property => $value) {
            if (in_array($property, $classProperties)) {
                $dtoData[$property] = $value;
            } else {
                $additionalData[$property] = $value;
            }
        }

        $properties = new self(...$dtoData);
        $properties->additional($additionalData);

        return $properties;
    }

    private static function getClassProperties(): array
    {
        $ref = new ReflectionClass(self::class);

        $properties = $ref->getProperties();

        return array_map(
            fn(ReflectionProperty $p) => $p->getName(),
            $properties,
        );
    }

    public function toArray(): array
    {
        return [
            'environment' => $this->environment,
            'platform' => $this->platform,
            ...$this->getAdditionalData()
        ];
    }
}
