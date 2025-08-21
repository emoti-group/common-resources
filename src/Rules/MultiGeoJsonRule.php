<?php

namespace Emoti\CommonResources\Rules;

use Closure;
use Emoti\CommonResources\DTO\GeoJsonGeometryDTO;
use Emoti\CommonResources\Services\LocationsHelper;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;

class MultiGeoJsonRule extends GeoJsonRule implements ValidationRule
{
    public function __construct(bool $validateIntersection = true)
    {
        parent::__construct($validateIntersection);
    }

    public function validate($attribute, $value, Closure $fail): void
    {
        if ($value === null) {
            $fail('Invalid JSON or unsupported type (expect GeoJSON geometry object).');
            return;
        }

        if (!is_array($value) || isset($value['type'])) {
            $fail('GeoJSONs array expected, but ' . gettype($value) . ' given.');
            return;
        }

        try {
            $geometries = array_map(
                function ($geometry) {
                    if (!isset($geometry['type'])) {
                        throw new Exception();
                    }

                    return GeoJsonGeometryDTO::fromValues(
                        $geometry['type'],
                        $geometry['coordinates'],
                        true,
                    );
                },
                $value,
            );
        } catch (Exception) {
            $fail('Incorrect structure for GeoJSON given.');
            return;
        }

        $complexGeometry = LocationsHelper::complexifyGeoJsonTypes($geometries);

        parent::validate($attribute, $complexGeometry->toArray(), $fail);
    }

    public function message(): string
    {
        return $this->error ?: 'Invalid complex GeoJSON geometry.';
    }
}
