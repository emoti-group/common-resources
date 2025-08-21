<?php

declare(strict_types=1);

namespace Emoti\CommonResources\DTO;

use Emoti\CommonResources\Enums\GeoJsonGeometryType;
use Emoti\CommonResources\Rules\GeoJsonRule;
use Emoti\CommonResources\Services\LocationsHelper;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * Represents a GeoJSON geometry with its type and coordinates.
 *
 * @property array<0|1, float|string> | list<array<0|1, float|string> | list<array<0|1, float|string> | list<array<0|1, float|string>>>> $coordinates The coordinates of the geometry.
 */
final class GeoJsonGeometryDTO extends Data
{
    public function __construct(
        public readonly GeoJsonGeometryType $type,
        public readonly array $coordinates,
    ) {}

    /**
     * Creates a GeoJsonGeometryDTO instance from given values.
     *
     * @param array<0|1, float|string> | list<array<0|1, float|string> | list<array<0|1, float|string> | list<array<0|1, float|string>>>> $coordinates The coordinates.
     */
    public static function fromValues(
        GeoJsonGeometryType|string $type,
        array $coordinates,
        bool $hasInvertedCoords = false,
    ): self {
        if (is_string($type)) {
            $type = GeoJsonGeometryType::tryFrom($type);
        }
        if (!$type instanceof GeoJsonGeometryType) {
            throw new InvalidArgumentException('Invalid GeoJSON geometry type provided.');
        }

        $coordinates = LocationsHelper::closeGeometryType($type, $coordinates);
        $coordinates = LocationsHelper::convertCoordsToFloat($coordinates);

        if (!$hasInvertedCoords) {
            $coordinates = LocationsHelper::invertCoords($type, $coordinates);
        }

        return new self(
            type: $type,
            coordinates: $coordinates,
        );
    }

    public function validateGeoJson(bool $validateIntersection = true): void
    {
        $validator = Validator::make(
            [
                'geometry' => $this->toArray(),
            ],
            [
                'geometry' => ['required', new GeoJsonRule($validateIntersection)],
            ],
        );

        if ($validator->fails()) {
            throw new InvalidArgumentException(
                'Invalid GeoJSON geometry: ' . implode(', ', $validator->errors()->all()),
            );
        }
    }

    /**
     * Simplifies the coordinates of the geometry using a specified epsilon value.
     *
     * @param float $epsilon The epsilon value used for simplification.
     */
    public function simplifyCoords(float $epsilon = 0.001): self
    {
        $simplifiedCoords = LocationsHelper::simplifyCoords($this->type, $this->coordinates, $epsilon);

        return new self(
            type: $this->type,
            coordinates: $simplifiedCoords,
        );
    }
}
